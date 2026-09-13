<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionItemResult;
use Illuminate\Support\Collection;

class InspectionPdfGenerator
{
    private const PAGE_WIDTH = 842;

    private const PAGE_HEIGHT = 595;

    private const MARGIN = 28;

    private const CONTENT_WIDTH = self::PAGE_WIDTH - (self::MARGIN * 2);

    private int $currentY = 0;

    /** @var array<int, string> */
    private array $pages = [];

    private string $content = '';

    public function generate(Inspection $inspection): string
    {
        $inspection->loadMissing(['inspector', 'buildings.results']);

        $this->pages = [];
        $this->startPage();
        $this->drawReportHeader($inspection);

        $isFirstBuilding = true;
        foreach ($inspection->buildings as $building) {
            if (! $isFirstBuilding) {
                $this->finishPage();
                $this->startPage();
                $this->drawReportHeader($inspection);
            }

            $isFirstBuilding = false;

            $this->sectionTitle('Port '.$building->building_name_snapshot);
            $this->metaLine('Status: '.$this->statusLabel($building->status));
            $this->tableHeader();

            $groupedResults = $building->results->groupBy('section_title_snapshot');
            foreach ($groupedResults as $sectionTitle => $results) {
                $this->ensureSpace(46);
                $this->drawSectionRow($sectionTitle);

                /** @var Collection<int, InspectionItemResult> $results */
                foreach ($results as $result) {
                    $this->drawResultRow($result);
                }
            }
        }

        $this->finishPage();

        return $this->buildPdf();
    }

    private function startPage(): void
    {
        $this->content = '';
        $this->currentY = self::PAGE_HEIGHT - self::MARGIN;
    }

    private function finishPage(): void
    {
        if ($this->content !== '') {
            $pageNumber = count($this->pages) + 1;
            $this->text(self::PAGE_WIDTH - self::MARGIN - 50, 18, 'Sida '.$pageNumber, 8);
            $this->pages[] = $this->content;
        }
    }

    private function ensureSpace(int $height): void
    {
        if ($this->currentY - $height < self::MARGIN) {
            $this->finishPage();
            $this->startPage();
        }
    }

    private function drawReportHeader(Inspection $inspection): void
    {
        $this->text(self::MARGIN, $this->currentY, 'CHECKLISTA SBA (systematiskt brandskyddsarbete) Kvartalskontroll', 15, true);
        $this->currentY -= 20;
        $this->text(self::MARGIN, $this->currentY, 'BRF VIGELSJÖHÖJDEN', 12, true);
        $this->currentY -= 24;

        $completed = $inspection->completed_at?->format('Y-m-d H:i') ?? '-';
        $inspector = $inspection->inspector?->name ?? '-';
        $this->metaLine('Kontrolldatum: '.$inspection->inspection_date->toDateString().'    Signatur: '.$inspector.'    Status: '.$this->statusLabel($inspection->status).'    Slutförd: '.$completed);
        $this->line(self::MARGIN, $this->currentY - 8, self::PAGE_WIDTH - self::MARGIN, $this->currentY - 8);
        $this->currentY -= 24;
    }

    private function sectionTitle(string $title): void
    {
        $this->text(self::MARGIN, $this->currentY, $title, 13, true);
        $this->currentY -= 18;
    }

    private function metaLine(string $text): void
    {
        $this->text(self::MARGIN, $this->currentY, $text, 9);
        $this->currentY -= 15;
    }

    private function tableHeader(): void
    {
        $x = self::MARGIN;
        $height = 22;
        $widths = $this->columnWidths();
        $headers = ['OK', 'ANMÄRKNING', 'KOMMENTAR', 'ÅTG DATUM'];

        $this->rect($x, $this->currentY - $height, self::CONTENT_WIDTH, $height);
        $this->text($x + 6, $this->currentY - 14, 'Kontrollpunkt', 8, true);

        $offset = $widths['item'];
        foreach ($headers as $index => $header) {
            $columnWidth = [$widths['ok'], $widths['remark'], $widths['comment'], $widths['date']][$index];
            $this->line($x + $offset, $this->currentY, $x + $offset, $this->currentY - $height);
            $this->text($x + $offset + 5, $this->currentY - 14, $header, 7, true);
            $offset += $columnWidth;
        }

        $this->currentY -= $height;
    }

    private function drawSectionRow(string $sectionTitle): void
    {
        $height = 18;
        $this->rect(self::MARGIN, $this->currentY - $height, self::CONTENT_WIDTH, $height);
        $this->text(self::MARGIN + 6, $this->currentY - 12, $sectionTitle, 8, true);
        $this->currentY -= $height;
    }

    private function drawResultRow(InspectionItemResult $result): void
    {
        $widths = $this->columnWidths();
        $itemLines = $this->wrap($result->item_text_snapshot, 62);
        $commentText = trim(implode(' ', array_filter([
            $result->status === 'not_applicable' ? 'Ej tillämplig' : null,
            $result->remark,
            $result->comment,
        ])));
        $commentLines = $this->wrap($commentText, 34);
        $lineCount = max(count($itemLines), count($commentLines), 1);
        $height = max(25, ($lineCount * 10) + 10);

        $this->ensureSpace($height + 6);

        $x = self::MARGIN;
        $top = $this->currentY;
        $bottom = $top - $height;
        $this->rect($x, $bottom, self::CONTENT_WIDTH, $height);

        $offsets = [
            $widths['item'],
            $widths['item'] + $widths['ok'],
            $widths['item'] + $widths['ok'] + $widths['remark'],
            $widths['item'] + $widths['ok'] + $widths['remark'] + $widths['comment'],
        ];

        foreach ($offsets as $offset) {
            $this->line($x + $offset, $top, $x + $offset, $bottom);
        }

        foreach ($itemLines as $index => $line) {
            $this->text($x + 5, $top - 12 - ($index * 10), $line, 7);
        }

        if ($result->status === 'ok') {
            $this->text($x + $widths['item'] + 13, $top - 15, 'X', 10, true);
        }

        if ($result->status === 'remark') {
            $this->text($x + $widths['item'] + $widths['ok'] + 33, $top - 15, 'X', 10, true);
        }

        $commentX = $x + $widths['item'] + $widths['ok'] + $widths['remark'] + 5;
        foreach ($commentLines as $index => $line) {
            $this->text($commentX, $top - 12 - ($index * 10), $line, 7);
        }

        if ($result->action_date !== null) {
            $dateX = $x + $widths['item'] + $widths['ok'] + $widths['remark'] + $widths['comment'] + 5;
            $this->text($dateX, $top - 12, $result->action_date->toDateString(), 7);
        }

        $this->currentY -= $height;
    }

    /**
     * @return array{item: int, ok: int, remark: int, comment: int, date: int}
     */
    private function columnWidths(): array
    {
        return [
            'item' => 360,
            'ok' => 36,
            'remark' => 82,
            'comment' => 230,
            'date' => 78,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function wrap(?string $text, int $maxLength): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [''];
        }

        return explode("\n", wordwrap($text, $maxLength, "\n", false));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Utkast',
            'in_progress' => 'Pågår',
            'completed' => 'Slutförd',
            'not_started' => 'Ej påbörjad',
            default => $status,
        };
    }

    private function text(int $x, int $y, string $text, int $size = 10, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->content .= sprintf("BT /%s %d Tf %d %d Td (%s) Tj ET\n", $font, $size, $x, $y, $this->escapeText($text));
    }

    private function line(int $x1, int $y1, int $x2, int $y2): void
    {
        $this->content .= sprintf("%d %d m %d %d l S\n", $x1, $y1, $x2, $y2);
    }

    private function rect(int $x, int $y, int $width, int $height): void
    {
        $this->content .= sprintf("%d %d %d %d re S\n", $x, $y, $width, $height);
    }

    private function escapeText(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $encoded = $encoded === false ? $text : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function buildPdf(): string
    {
        $objects = [];
        $pageObjectIds = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = null;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $pageContent) {
            $contentObjectId = count($objects) + 1;
            $objects[] = '<< /Length '.strlen($pageContent)." >>\nstream\n".$pageContent.'endstream';

            $pageObjectIds[] = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObjectId.' 0 R >>';
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageObjectIds)).'] /Count '.count($pageObjectIds).' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectId = $index + 1;
            $pdf .= $objectId." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
