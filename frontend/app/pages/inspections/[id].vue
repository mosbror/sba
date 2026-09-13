<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()

useHead({ title: 'Brandskyddskontroll' })

type Result = {
  id: number
  status: 'unchecked' | 'ok' | 'remark' | 'not_applicable'
}

type InspectionBuilding = {
  id: number
  name: string
  status: 'not_started' | 'in_progress' | 'completed'
  results: Result[]
}

type Inspection = {
  id: number
  title: string
  inspection_date: string
  status: 'draft' | 'in_progress' | 'completed'
  completed_at: string | null
  buildings_count: number
  handled_count: number
  total_count: number
  missing_count: number
  buildings: InspectionBuilding[]
}

type CompletionError = {
  message?: string
  data?: {
    message?: string
    missing_buildings?: Array<{ id: number; name: string; missing_count: number }>
  }
}

const { data: inspection, pending, error, refresh } = await useFetch<Inspection>(`${config.public.apiBase}/inspections/${route.params.id}`)
const isCompleting = ref(false)
const completeError = ref('')
const missingBuildings = ref<Array<{ id: number; name: string; missing_count: number }>>([])

const isCompleted = computed(() => inspection.value?.status === 'completed')
const canComplete = computed(() => Boolean(inspection.value && inspection.value.status !== 'completed' && inspection.value.missing_count === 0 && !isCompleting.value))
const progressPercent = computed(() => {
  if (!inspection.value || inspection.value.total_count === 0) {
    return 0
  }

  return Math.round((inspection.value.handled_count / inspection.value.total_count) * 100)
})

function statusLabel(status: Inspection['status'] | InspectionBuilding['status']): string {
  return {
    draft: 'Utkast',
    in_progress: 'Pågår',
    completed: 'Slutförd',
    not_started: 'Ej påbörjad'
  }[status]
}

function buildingProgress(building: InspectionBuilding): string {
  const total = building.results.length
  const handled = building.results.filter((result) => result.status !== 'unchecked').length
  return `${handled}/${total}`
}

function buildingMarker(building: InspectionBuilding): string {
  if (building.status === 'completed') {
    return '✓ Klar'
  }

  if (building.status === 'in_progress') {
    return '● Pågår'
  }

  return '○ Ej påbörjad'
}

function pdfUrl(): string {
  return `${config.public.apiBase}/inspections/${route.params.id}/pdf`
}

async function completeInspection(): Promise<void> {
  if (!inspection.value || isCompleted.value) {
    return
  }

  completeError.value = ''
  missingBuildings.value = []
  isCompleting.value = true

  try {
    inspection.value = await $fetch<Inspection>(`${config.public.apiBase}/inspections/${inspection.value.id}/complete`, {
      method: 'POST'
    })
    await refresh()
  } catch (error) {
    const completionError = error as CompletionError
    completeError.value = completionError.data?.message || completionError.message || 'Kunde inte slutföra kontrollen.'
    missingBuildings.value = completionError.data?.missing_buildings || []
  } finally {
    isCompleting.value = false
  }
}
</script>

<template>
  <NuxtPage v-if="route.params.buildingId" />
  <main v-else class="page-shell">
    <nav class="top-nav">
      <NuxtLink to="/inspections">Tidigare kontroller</NuxtLink>
      <NuxtLink to="/inspections/new">Ny kontroll</NuxtLink>
      <NuxtLink to="/">Översikt</NuxtLink>
    </nav>

    <section class="content-card">
      <p class="eyebrow">Brandskyddskontroll</p>
      <p v-if="pending">Hämtar kontroll…</p>
      <p v-else-if="error" class="error-text">Kunde inte hämta kontrollen.</p>
      <template v-else-if="inspection">
        <div class="page-heading">
          <div>
            <h1>{{ inspection.title }}</h1>
            <p>Kontrolldatum: {{ inspection.inspection_date }} · Status: {{ statusLabel(inspection.status) }}</p>
            <p v-if="inspection.completed_at" class="success-text">Slutförd {{ new Date(inspection.completed_at).toLocaleString('sv-SE') }}</p>
          </div>
          <span class="status-pill" :class="`status-${inspection.status}`">{{ statusLabel(inspection.status) }}</span>
        </div>

        <section class="completion-panel">
          <div>
            <h2>Framsteg</h2>
            <p>{{ inspection.handled_count }}/{{ inspection.total_count }} punkter hanterade. {{ inspection.missing_count }} återstår.</p>
            <div class="progress-track" aria-hidden="true">
              <span :style="{ width: `${progressPercent}%` }" />
            </div>
          </div>

          <div class="action-row">
            <button v-if="!isCompleted" type="button" :disabled="!canComplete" @click="completeInspection">
              {{ isCompleting ? 'Slutför…' : 'Slutför kontroll' }}
            </button>
            <a class="button-link secondary" :href="pdfUrl()">Ladda ner PDF</a>
          </div>

          <p v-if="isCompleted" class="success-text">Kontrollen är slutförd och låst. PDF-filen kan laddas ner för arkiv.</p>
          <p v-else-if="inspection.missing_count > 0" class="helper-text">Alla kontrollpunkter måste markeras som OK, Anmärkning eller Ej tillämplig innan kontrollen kan slutföras.</p>
          <p v-if="completeError" class="error-text">{{ completeError }}</p>
          <ul v-if="missingBuildings.length > 0" class="missing-list">
            <li v-for="building in missingBuildings" :key="building.id">
              {{ building.name }}: {{ building.missing_count }} punkter återstår
            </li>
          </ul>
        </section>

        <h2>Byggnader</h2>
        <div class="building-list">
          <NuxtLink
            v-for="building in inspection.buildings"
            :key="building.id"
            class="building-row"
            :to="`/inspections/${inspection.id}/buildings/${building.id}`"
          >
            <strong>{{ building.name }}</strong>
            <span>{{ buildingMarker(building) }}</span>
            <span>{{ buildingProgress(building) }} punkter</span>
            <span>{{ isCompleted ? 'Visa' : 'Fyll i' }}</span>
          </NuxtLink>
        </div>
      </template>
    </section>
  </main>
</template>
