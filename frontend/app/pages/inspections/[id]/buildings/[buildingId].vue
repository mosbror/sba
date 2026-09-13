<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()

useHead({ title: 'Fyll i byggnad' })

type ResultStatus = 'unchecked' | 'ok' | 'remark' | 'not_applicable'

type Result = {
  id: number
  section: string
  text: string
  status: ResultStatus
  remark: string | null
  comment: string | null
  action_date: string | null
}

type InspectionBuilding = {
  id: number
  name: string
  status: string
  results: Result[]
}

type Inspection = {
  id: number
  title: string
  inspection_date: string
  status: 'draft' | 'in_progress' | 'completed'
  buildings: InspectionBuilding[]
}

const saveState = ref<Record<number, string>>({})
const { data: inspection, pending, error, refresh } = await useFetch<Inspection>(`${config.public.apiBase}/inspections/${route.params.id}`)

const building = computed(() => {
  return inspection.value?.buildings.find((item) => String(item.id) === String(route.params.buildingId))
})

const isCompleted = computed(() => inspection.value?.status === 'completed')

const sections = computed(() => {
  const grouped = new Map<string, Result[]>()
  for (const result of building.value?.results ?? []) {
    grouped.set(result.section, [...(grouped.get(result.section) ?? []), result])
  }

  return Array.from(grouped.entries()).map(([title, results]) => ({ title, results }))
})

const handledCount = computed(() => building.value?.results.filter((result) => result.status !== 'unchecked').length ?? 0)
const totalCount = computed(() => building.value?.results.length ?? 0)

async function saveResult(result: Result, changes: Partial<Result>): Promise<void> {
  if (isCompleted.value) {
    saveState.value[result.id] = 'Slutförd kontroll är låst'
    return
  }

  Object.assign(result, changes)
  saveState.value[result.id] = 'Sparar…'

  try {
    await $fetch(`${config.public.apiBase}/inspection-results/${result.id}`, {
      method: 'PUT',
      body: {
        status: result.status,
        remark: result.remark,
        comment: result.comment,
        action_date: result.action_date
      }
    })
    saveState.value[result.id] = 'Sparad'
    await refresh()
  } catch {
    saveState.value[result.id] = 'Kunde inte spara'
  }
}
</script>

<template>
  <main class="page-shell checklist-page">
    <nav class="top-nav">
      <NuxtLink :to="`/inspections/${route.params.id}`">Till byggnader</NuxtLink>
      <NuxtLink to="/inspections">Tidigare kontroller</NuxtLink>
      <NuxtLink to="/">Översikt</NuxtLink>
    </nav>

    <section class="content-card">
      <p class="eyebrow">Byggnad</p>
      <p v-if="pending">Hämtar checklista…</p>
      <p v-else-if="error" class="error-text">Kunde inte hämta checklistan.</p>
      <template v-else-if="building && inspection">
        <header class="building-header">
          <div>
            <h1>{{ building.name }}</h1>
            <p>{{ inspection.title }} · {{ handledCount }}/{{ totalCount }} hanterade punkter</p>
            <p v-if="isCompleted" class="success-text">Kontrollen är slutförd och låst.</p>
          </div>
        </header>

        <section v-for="section in sections" :key="section.title" class="checklist-section">
          <h2>{{ section.title }}</h2>
          <article v-for="result in section.results" :key="result.id" class="checklist-item">
            <p>{{ result.text }}</p>
            <div class="status-actions" role="group" aria-label="Status">
              <button type="button" :disabled="isCompleted" :class="{ selected: result.status === 'ok' }" @click="saveResult(result, { status: 'ok' })">OK</button>
              <button type="button" :disabled="isCompleted" :class="{ selected: result.status === 'remark' }" @click="saveResult(result, { status: 'remark' })">Anmärkning</button>
              <button type="button" :disabled="isCompleted" :class="{ selected: result.status === 'not_applicable' }" @click="saveResult(result, { status: 'not_applicable' })">Ej tillämplig</button>
            </div>

            <div v-if="result.status === 'remark'" class="remark-fields">
              <label>
                Anmärkning
                <input v-model="result.remark" :disabled="isCompleted" type="text" @blur="saveResult(result, { remark: result.remark })">
              </label>
              <label>
                Kommentar
                <textarea v-model="result.comment" :disabled="isCompleted" rows="3" @blur="saveResult(result, { comment: result.comment })" />
              </label>
              <label>
                Åtgärdsdatum
                <input v-model="result.action_date" :disabled="isCompleted" type="date" @change="saveResult(result, { action_date: result.action_date })">
              </label>
            </div>

            <small>{{ saveState[result.id] || (result.status === 'unchecked' ? 'Ej kontrollerad' : 'Sparad') }}</small>
          </article>
        </section>
      </template>
      <p v-else class="error-text">Byggnaden kunde inte hittas.</p>
    </section>
  </main>
</template>
