<?php

use App\Models\Building;
use App\Models\ChecklistTemplate;
use App\Models\Inspection;
use App\Models\InspectionItemResult;
use App\Models\User;
use App\Services\InspectionPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

$inspectionProgress = function (Inspection $inspection): array {
    $inspection->loadMissing(['buildings.results']);

    $totalCount = $inspection->buildings->sum(fn ($building): int => $building->results->count());
    $handledCount = $inspection->buildings->sum(fn ($building): int => $building->results->where('status', '!=', 'unchecked')->count());
    $missingCount = $totalCount - $handledCount;

    return [
        'total_count' => $totalCount,
        'handled_count' => $handledCount,
        'missing_count' => $missingCount,
        'buildings_count' => $inspection->buildings->count(),
    ];
};

$inspectionPayload = function (Inspection $inspection) use ($inspectionProgress): array {
    $inspection->load(['buildings.results']);
    $progress = $inspectionProgress($inspection);

    return [
        'id' => $inspection->id,
        'title' => $inspection->title,
        'inspection_date' => $inspection->inspection_date->toDateString(),
        'status' => $inspection->status,
        'completed_at' => $inspection->completed_at?->toIso8601String(),
        ...$progress,
        'buildings' => $inspection->buildings->map(fn ($building) => [
            'id' => $building->id,
            'building_id' => $building->building_id,
            'name' => $building->building_name_snapshot,
            'status' => $building->status,
            'results' => $building->results->map(fn ($result) => [
                'id' => $result->id,
                'section' => $result->section_title_snapshot,
                'text' => $result->item_text_snapshot,
                'status' => $result->status,
                'remark' => $result->remark,
                'comment' => $result->comment,
                'action_date' => $result->action_date?->toDateString(),
            ])->values(),
        ])->values(),
    ];
};

$inspectionSummary = function (Inspection $inspection) use ($inspectionProgress): array {
    $progress = $inspectionProgress($inspection);

    return [
        'id' => $inspection->id,
        'title' => $inspection->title,
        'inspection_date' => $inspection->inspection_date->toDateString(),
        'status' => $inspection->status,
        'completed_at' => $inspection->completed_at?->toIso8601String(),
        ...$progress,
    ];
};

Route::get('/health', function (): array {
    return ['status' => 'ok'];
});

Route::get('/buildings', function () {
    return Building::query()
        ->where('active', true)
        ->orderBy('sort_order')
        ->get(['id', 'name', 'sort_order']);
});

Route::get('/checklist-templates/current', function () {
    $template = ChecklistTemplate::query()
        ->where('active', true)
        ->with(['sections.items' => fn ($query) => $query->where('active', true)])
        ->orderByDesc('version')
        ->firstOrFail();

    return [
        'id' => $template->id,
        'name' => $template->name,
        'version' => $template->version,
        'sections' => $template->sections->map(fn ($section) => [
            'id' => $section->id,
            'title' => $section->title,
            'sort_order' => $section->sort_order,
            'items' => $section->items->map(fn ($item) => [
                'id' => $item->id,
                'text' => $item->text,
                'sort_order' => $item->sort_order,
            ])->values(),
        ])->values(),
    ];
});

Route::get('/inspections', function () use ($inspectionSummary) {
    return Inspection::query()
        ->with(['buildings.results'])
        ->orderByDesc('inspection_date')
        ->orderByDesc('id')
        ->get()
        ->map($inspectionSummary)
        ->values();
});

Route::post('/inspections', function (Request $request) use ($inspectionPayload) {
    $data = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'inspection_date' => ['required', 'date'],
        'building_ids' => ['required', 'array', 'min:1'],
        'building_ids.*' => ['integer', Rule::exists('buildings', 'id')->where('active', true)],
    ]);

    $buildingIds = array_values(array_unique($data['building_ids']));
    $template = ChecklistTemplate::query()
        ->where('active', true)
        ->with(['sections.items' => fn ($query) => $query->where('active', true)])
        ->orderByDesc('version')
        ->firstOrFail();
    $inspector = User::query()->orderBy('id')->firstOrFail();

    $inspection = DB::transaction(function () use ($data, $buildingIds, $template, $inspector): Inspection {
        $inspection = Inspection::query()->create([
            'checklist_template_id' => $template->id,
            'title' => $data['title'],
            'inspection_date' => $data['inspection_date'],
            'status' => 'draft',
            'inspector_user_id' => $inspector->id,
        ]);

        $buildings = Building::query()
            ->whereIn('id', $buildingIds)
            ->orderBy('sort_order')
            ->get();

        foreach ($buildings as $building) {
            $inspectionBuilding = $inspection->buildings()->create([
                'building_id' => $building->id,
                'building_name_snapshot' => $building->name,
                'sort_order_snapshot' => $building->sort_order,
                'status' => 'not_started',
            ]);

            foreach ($template->sections as $section) {
                foreach ($section->items as $item) {
                    $inspectionBuilding->results()->create([
                        'checklist_item_id' => $item->id,
                        'section_title_snapshot' => $section->title,
                        'item_text_snapshot' => $item->text,
                        'section_sort_order_snapshot' => $section->sort_order,
                        'item_sort_order_snapshot' => $item->sort_order,
                        'status' => 'unchecked',
                    ]);
                }
            }
        }

        return $inspection;
    });

    return response()->json($inspectionPayload($inspection), 201);
});

Route::post('/inspections/{inspection}/complete', function (Inspection $inspection) use ($inspectionPayload) {
    $inspection->load(['buildings.results']);

    $missingByBuilding = $inspection->buildings
        ->map(fn ($building) => [
            'id' => $building->id,
            'name' => $building->building_name_snapshot,
            'missing_count' => $building->results->where('status', 'unchecked')->count(),
        ])
        ->filter(fn (array $building): bool => $building['missing_count'] > 0)
        ->values();

    if ($missingByBuilding->isNotEmpty()) {
        return response()->json([
            'message' => 'Kontrollen kan inte slutföras förrän alla punkter är hanterade.',
            'missing_buildings' => $missingByBuilding,
        ], 422);
    }

    DB::transaction(function () use ($inspection): void {
        foreach ($inspection->buildings as $building) {
            $building->update([
                'status' => 'completed',
                'started_at' => $building->started_at ?? now(),
                'completed_at' => $building->completed_at ?? now(),
            ]);
        }

        $inspection->update([
            'status' => 'completed',
            'completed_at' => $inspection->completed_at ?? now(),
        ]);
    });

    return $inspectionPayload($inspection->fresh());
});

Route::get('/inspections/{inspection}/pdf', function (Inspection $inspection, InspectionPdfGenerator $generator) {
    $pdf = $generator->generate($inspection);
    $filename = 'sba-kontroll-'.$inspection->id.'.pdf';

    return response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        'Content-Length' => (string) strlen($pdf),
    ]);
});

Route::get('/inspections/{inspection}', function (Inspection $inspection) use ($inspectionPayload) {
    return $inspectionPayload($inspection);
});

Route::put('/inspection-results/{result}', function (Request $request, InspectionItemResult $result) {
    $data = $request->validate([
        'status' => ['required', Rule::in(['unchecked', 'ok', 'remark', 'not_applicable'])],
        'remark' => ['nullable', 'string', 'max:255'],
        'comment' => ['nullable', 'string'],
        'action_date' => ['nullable', 'date'],
    ]);

    $building = $result->inspectionBuilding()->with(['results', 'inspection'])->firstOrFail();
    if ($building->inspection->status === 'completed') {
        return response()->json([
            'message' => 'Slutförda kontroller kan inte ändras.',
        ], 409);
    }

    $result->update($data);

    $building->refresh()->load('results');
    $hasUnchecked = $building->results->contains(fn ($item) => $item->status === 'unchecked');
    $building->update([
        'status' => $hasUnchecked ? 'in_progress' : 'completed',
        'started_at' => $building->started_at ?? now(),
        'completed_at' => $hasUnchecked ? null : now(),
    ]);

    $inspection = $building->inspection;
    if ($inspection->status === 'draft') {
        $inspection->update(['status' => 'in_progress']);
    }

    return [
        'id' => $result->id,
        'status' => $result->status,
        'remark' => $result->remark,
        'comment' => $result->comment,
        'action_date' => $result->action_date?->toDateString(),
        'building_status' => $building->fresh()->status,
    ];
});
