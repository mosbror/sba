<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'SBA-admin',
                'email' => null,
                'password' => Hash::make('password'),
            ],
        );

        foreach (['1A', '1B', '3A', '3B', '5A', '5B'] as $index => $name) {
            Building::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => ($index + 1) * 10, 'active' => true],
            );
        }

        $template = ChecklistTemplate::query()->updateOrCreate(
            ['name' => 'Kvartalskontroll SBA', 'version' => 1],
            ['active' => true],
        );

        $template->sections()
            ->where('title', 'TVÄTTSTUGA 5A')
            ->update(['title' => 'TVÄTTSTUGA']);

        DB::table('inspection_item_results')
            ->where('section_title_snapshot', 'TVÄTTSTUGA 5A')
            ->whereIn('inspection_building_id', function ($query): void {
                $query->select('inspection_buildings.id')
                    ->from('inspection_buildings')
                    ->join('inspections', 'inspections.id', '=', 'inspection_buildings.inspection_id')
                    ->where('inspections.status', '!=', 'completed');
            })
            ->update(['section_title_snapshot' => 'TVÄTTSTUGA']);

        $sections = [
            'TRAPPHUS' => [
                'Trapphus är fritt från brännbart mtrl samt mtrl som försvårar utrymning eller räddningstjänstens arbete vid brand.',
                'Entrédörrar fungerar och stängs ordentligt',
            ],
            'CYKEL/BARNVAGNSFÖRRÅD' => [
                'Skall bara finnas sådant som BRF-regler tillåter.',
                'Dörrar fungerar och stängs ordentligt.',
                'Alla lampor är hela och fungerar.',
                'Utrymingsskyltar sitter på insidan av dörren.',
            ],
            'FÖRRÅDS/TEKNIKGÅNGAR' => [
                'Gångarna är fria från brännbart mtrl samt mtrl som förhindrar utrymning.',
                'Dörrar fungerar och stängs ordentligt.',
                'Brandvarnare i drift och fungerar.',
                'Alla lampor är hela och fungerar.',
                'Utrymingsskyltar sitter på insidan av dörren.',
            ],
            'TVÄTTSTUGA' => [
                'Brandvarnare i drift och fungerar.',
                'Dörr fungerar och stängs ordentligt.',
                '"Rent och snyggt"',
            ],
            'UTOMHUS' => [
                'Markområden intill husens fasader är fria från brännbart mtrl.',
                'Fria vägar för räddningstjänsten.',
                'Utomhusbelysning fungerar.',
            ],
            'LÄGENHETER' => [
                'Information om alla medlemmars ansvar i föreningens SBA lämnas till nyinflyttade och informeras till samtliga 1 gång/år.',
                'Påminnelse 1 gång/år till alla medlemmar om att testa sina brandvarnare och vid behov byta batteri/brandvarnare.',
            ],
        ];

        foreach ($sections as $sectionIndex => $items) {
            $section = $template->sections()->updateOrCreate(
                ['title' => $sectionIndex],
                ['sort_order' => array_search($sectionIndex, array_keys($sections), true) * 10],
            );

            foreach ($items as $itemIndex => $text) {
                $section->items()->updateOrCreate(
                    ['text' => $text],
                    ['sort_order' => ($itemIndex + 1) * 10, 'active' => true],
                );
            }
        }
    }
}
