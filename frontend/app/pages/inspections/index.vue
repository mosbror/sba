<script setup lang="ts">
const config = useRuntimeConfig()

useHead({ title: 'Tidigare kontroller' })

type InspectionSummary = {
  id: number
  title: string
  inspection_date: string
  status: 'draft' | 'in_progress' | 'completed'
  completed_at: string | null
  buildings_count: number
  handled_count: number
  total_count: number
  missing_count: number
}

const { data: inspections, pending, error, refresh } = await useFetch<InspectionSummary[]>(`${config.public.apiBase}/inspections`, {
  default: () => []
})

function statusLabel(status: InspectionSummary['status']): string {
  return {
    draft: 'Utkast',
    in_progress: 'Pågår',
    completed: 'Slutförd'
  }[status]
}

function statusClass(status: InspectionSummary['status']): string {
  return `status-${status}`
}

function pdfUrl(inspection: InspectionSummary): string {
  return `${config.public.apiBase}/inspections/${inspection.id}/pdf`
}

onMounted(() => {
  refresh()
})
</script>

<template>
  <main class="page-shell">
    <nav class="top-nav">
      <NuxtLink to="/">Översikt</NuxtLink>
      <NuxtLink to="/inspections/new">Ny kontroll</NuxtLink>
    </nav>

    <section class="content-card">
      <p class="eyebrow">Brandskyddskontroller</p>
      <h1>Tidigare kontroller</h1>
      <p>Öppna en pågående kontroll, fortsätt där du slutade eller ladda ner en PDF för arkivet.</p>

      <p v-if="pending">Hämtar kontroller…</p>
      <p v-else-if="error" class="error-text">Kunde inte hämta tidigare kontroller.</p>
      <div v-else-if="inspections.length > 0" class="inspection-list">
        <article v-for="inspection in inspections" :key="inspection.id" class="inspection-card">
          <div>
            <p class="eyebrow">{{ inspection.inspection_date }}</p>
            <h2>{{ inspection.title }}</h2>
            <p>
              {{ inspection.buildings_count }} byggnader ·
              {{ inspection.handled_count }}/{{ inspection.total_count }} punkter hanterade
            </p>
            <p v-if="inspection.completed_at" class="helper-text">Slutförd {{ new Date(inspection.completed_at).toLocaleString('sv-SE') }}</p>
          </div>

          <div class="inspection-actions">
            <span class="status-pill" :class="statusClass(inspection.status)">{{ statusLabel(inspection.status) }}</span>
            <NuxtLink class="button-link" :to="`/inspections/${inspection.id}`">
              {{ inspection.status === 'completed' ? 'Visa kontroll' : 'Fortsätt' }}
            </NuxtLink>
            <a class="button-link secondary" :href="pdfUrl(inspection)">Ladda ner PDF</a>
          </div>
        </article>
      </div>

      <div v-else class="empty-state">
        <h2>Inga kontroller ännu</h2>
        <p>Skapa första kvartalskontrollen för att komma igång.</p>
        <NuxtLink class="button-link" to="/inspections/new">Ny kontroll</NuxtLink>
      </div>
    </section>
  </main>
</template>
