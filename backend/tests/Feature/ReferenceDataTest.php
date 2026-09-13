<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Inspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_buildings_are_available_from_api(): void
    {
        $this->seed();

        $this->getJson('/api/buildings')
            ->assertOk()
            ->assertJsonPath('0.name', '1A')
            ->assertJsonPath('4.name', '5A')
            ->assertJsonPath('5.name', '5B')
            ->assertJsonCount(6);
    }

    public function test_current_checklist_template_matches_reference_sections(): void
    {
        $this->seed();

        $this->getJson('/api/checklist-templates/current')
            ->assertOk()
            ->assertJsonPath('name', 'Kvartalskontroll SBA')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('sections.0.title', 'TRAPPHUS')
            ->assertJsonPath('sections.1.title', 'CYKEL/BARNVAGNSFÖRRÅD')
            ->assertJsonPath('sections.2.title', 'FÖRRÅDS/TEKNIKGÅNGAR')
            ->assertJsonPath('sections.3.title', 'TVÄTTSTUGA')
            ->assertJsonPath('sections.4.title', 'UTOMHUS')
            ->assertJsonPath('sections.5.title', 'LÄGENHETER')
            ->assertJsonCount(2, 'sections.0.items')
            ->assertJsonCount(4, 'sections.1.items')
            ->assertJsonCount(5, 'sections.2.items')
            ->assertJsonCount(3, 'sections.3.items')
            ->assertJsonCount(3, 'sections.4.items')
            ->assertJsonCount(2, 'sections.5.items');
    }

    public function test_creating_inspection_snapshots_selected_buildings_and_checklist_items(): void
    {
        $this->seed();

        $buildingIds = Building::query()
            ->whereIn('name', ['1A', '5B'])
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->postJson('/api/inspections', [
            'title' => 'Kvartalskontroll Q3 2026',
            'inspection_date' => '2026-09-13',
            'building_ids' => $buildingIds,
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'Kvartalskontroll Q3 2026')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('buildings.0.name', '1A')
            ->assertJsonPath('buildings.1.name', '5B');

        $inspection = Inspection::query()->with('buildings.results')->firstOrFail();

        $this->assertCount(2, $inspection->buildings);
        $this->assertEquals('5B', $inspection->buildings->last()->building_name_snapshot);
        $this->assertCount(19, $inspection->buildings->first()->results);
        $this->assertEquals('unchecked', $inspection->buildings->first()->results->first()->status);
    }

    public function test_inspection_can_be_read_and_checklist_result_saved(): void
    {
        $this->seed();

        $buildingId = Building::query()->where('name', '1A')->valueOrFail('id');

        $created = $this->postJson('/api/inspections', [
            'title' => 'Kvartalskontroll Q3 2026',
            'inspection_date' => '2026-09-13',
            'building_ids' => [$buildingId],
        ])->json();

        $this->getJson('/api/inspections/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('buildings.0.name', '1A')
            ->assertJsonPath('buildings.0.results.0.status', 'unchecked');

        $resultId = $created['buildings'][0]['results'][0]['id'];

        $this->putJson('/api/inspection-results/'.$resultId, [
            'status' => 'remark',
            'remark' => 'Testanmärkning',
            'comment' => 'Testkommentar',
            'action_date' => '2026-09-20',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'remark')
            ->assertJsonPath('building_status', 'in_progress');

        $this->assertDatabaseHas('inspection_item_results', [
            'id' => $resultId,
            'status' => 'remark',
            'remark' => 'Testanmärkning',
        ]);
    }

    public function test_inspections_can_be_listed_completed_and_downloaded_as_pdf(): void
    {
        $this->seed();

        $buildingIds = Building::query()
            ->whereIn('name', ['1A', '1B'])
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $created = $this->postJson('/api/inspections', [
            'title' => 'Kvartalskontroll Q3 2026',
            'inspection_date' => '2026-09-13',
            'building_ids' => $buildingIds,
        ])->json();

        $this->getJson('/api/inspections')
            ->assertOk()
            ->assertJsonPath('0.id', $created['id'])
            ->assertJsonPath('0.status', 'draft')
            ->assertJsonPath('0.missing_count', 38);

        $this->postJson('/api/inspections/'.$created['id'].'/complete')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kontrollen kan inte slutföras förrän alla punkter är hanterade.')
            ->assertJsonPath('missing_buildings.0.name', '1A');

        foreach ($created['buildings'] as $building) {
            foreach ($building['results'] as $result) {
                $this->putJson('/api/inspection-results/'.$result['id'], [
                    'status' => 'ok',
                    'remark' => null,
                    'comment' => null,
                    'action_date' => null,
                ])->assertOk();
            }
        }

        $this->postJson('/api/inspections/'.$created['id'].'/complete')
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('missing_count', 0);

        $pdfResponse = $this->get('/api/inspections/'.$created['id'].'/pdf');
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
        $pdf = $pdfResponse->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Type \/Page\b/', $pdf));

        $this->putJson('/api/inspection-results/'.$created['buildings'][0]['results'][0]['id'], [
            'status' => 'remark',
            'remark' => 'För sent',
            'comment' => null,
            'action_date' => null,
        ])->assertStatus(409);
    }
}
