# SBA implementation plan

## Reference checklist structure

Source files inspected:
- `Checklista SBA.xlsx`
- `Checklista SBA.pdf`

The checklist is a one-sheet/one-page quarterly SBA form for `BRF VIGELSJÖHÖJDEN` with header fields for `Kontrolldatum`, `Signatur`, and `Port nr`. The table columns are `OK`, `ANMÄRKNING`, `KOMMENTAR`, and `ÅTG DATUM`.

Initial sections/items from the XLS:

- TRAPPHUS
  - Trapphus är fritt från brännbart mtrl samt mtrl som försvårar utrymning eller räddningstjänstens arbete vid brand.
  - Entrédörrar fungerar och stängs ordentligt
- CYKEL/BARNVAGNSFÖRRÅD
  - Skall bara finnas sådant som BRF-regler tillåter.
  - Dörrar fungerar och stängs ordentligt.
  - Alla lampor är hela och fungerar.
  - Utrymingsskyltar sitter på insidan av dörren.
- FÖRRÅDS/TEKNIKGÅNGAR
  - Gångarna är fria från brännbart mtrl samt mtrl som förhindrar utrymning.
  - Dörrar fungerar och stängs ordentligt.
  - Brandvarnare i drift och fungerar.
  - Alla lampor är hela och fungerar.
  - Utrymingsskyltar sitter på insidan av dörren.
- TVÄTTSTUGA
  - Brandvarnare i drift och fungerar.
  - Dörr fungerar och stängs ordentligt.
  - "Rent och snyggt"
- UTOMHUS
  - Markområden intill husens fasader är fria från brännbart mtrl.
  - Fria vägar för räddningstjänsten.
  - Utomhusbelysning fungerar.
- LÄGENHETER
  - Information om alla medlemmars ansvar i föreningens SBA lämnas till nyinflyttade och informeras till samtliga 1 gång/år.
  - Påminnelse 1 gång/år till alla medlemmar om att testa sina brandvarnare och vid behov byta batteri/brandvarnare.

## Proposed schema

- `users`: id, username, name, password, timestamps
- `buildings`: id, name, sort_order, active, timestamps
- `checklist_templates`: id, name, version, active, timestamps
- `checklist_sections`: id, checklist_template_id, title, sort_order, timestamps
- `checklist_items`: id, checklist_section_id, text, sort_order, active, timestamps
- `inspections`: id, checklist_template_id, title, inspection_date, status, inspector_user_id, completed_at, timestamps
- `inspection_buildings`: id, inspection_id, building_id, status, started_at, completed_at, timestamps
- `inspection_item_results`: id, inspection_building_id, checklist_item_id, section_title_snapshot, item_text_snapshot, sort_order_snapshot, status, remark, comment, action_date, timestamps

Historical integrity: create `inspection_item_results` for every selected building and checklist item when an inspection is created, including snapshot columns for section/item text and ordering. The live template can then evolve without changing old reports.

## Page/navigation structure

- `/login`: username/password login.
- `/`: dashboard with active and historic inspections.
- `/inspections/new`: create inspection, date/title, select buildings.
- `/inspections/[id]`: inspection overview with building progress.
- `/inspections/[id]/buildings/[buildingId]`: touch-first checklist for one building, with next/previous building controls.
- `/inspections/[id]/complete`: completion validation and explicit finish action.
- `/inspections/[id]/pdf`: PDF preview/download route, backed by server-side PDF generation.
- `/settings/buildings`: minimal building admin if included in v1.

## Technology choices

- Backend: Laravel, because it gives migrations, validation, auth/session handling, API resources, tests, queue/cache hooks, and PDF package integration without custom plumbing.
- Frontend: Nuxt 3 with TypeScript and Vue 3 Composition API.
- Auth: Laravel session-cookie auth via Sanctum for SPA/API. Simple username/password user table; no roles in v1.
- PDF: Laravel renders an HTML Blade report and converts server-side using a Chromium-based renderer via Spatie Browsershot if Docker can include Chromium reliably. Fallback is Dompdf if Chromium proves too heavy, but Chromium gives better A4 layout control.
- Storage: MySQL 8 for app data, Redis included for future cache/session/queue use but not required for the first functional path.

## Assumptions/ambiguities

- The XLS/PDF are the authoritative checklist source; `Utrymingsskyltar` is preserved initially even though it may be a typo.
- No complete building list is present in the supplied files. Seed only example buildings until the real list is provided.
- `TVÄTTSTUGA` is the normalized checklist section name; if a building does not have a laundry room, model that through `not_applicable` rather than hardcoding building-specific UI.
- PDF option A is the initial target: one generated PDF with one checklist section/page set per building.
