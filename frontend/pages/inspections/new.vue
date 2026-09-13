<script setup lang="ts">
const config = useRuntimeConfig()

useHead({ title: 'Ny brandskyddskontroll' })

type Building = {
  id: number
  name: string
  sort_order: number
}

type CreatedInspection = {
  id: number
  title: string
  inspection_date: string
  status: string
  buildings: Array<{ id: number; name: string; status: string }>
}

const today = new Date().toISOString().slice(0, 10)
const title = ref('Kvartalskontroll')
const inspectionDate = ref(today)
const selectedBuildings = ref<number[]>([])
const isSubmitting = ref(false)
const submitError = ref('')

const { data: buildings, pending, error } = await useFetch<Building[]>(`${config.public.apiBase}/buildings`, {
  default: () => []
})

const canCreate = computed(() => {
  return title.value.trim().length > 0 && inspectionDate.value.length > 0 && selectedBuildings.value.length > 0 && !isSubmitting.value
})

watch(buildings, (availableBuildings) => {
  if (selectedBuildings.value.length === 0 && availableBuildings.length > 0) {
    selectedBuildings.value = availableBuildings.map((building) => building.id)
  }
}, { immediate: true })

function toggleBuilding(id: number): void {
  if (selectedBuildings.value.includes(id)) {
    selectedBuildings.value = selectedBuildings.value.filter((buildingId) => buildingId !== id)
    return
  }

  selectedBuildings.value = [...selectedBuildings.value, id]
}

async function createInspection(): Promise<void> {
  if (!canCreate.value) {
    return
  }

  submitError.value = ''
  isSubmitting.value = true

  try {
    const inspection = await $fetch<CreatedInspection>(`${config.public.apiBase}/inspections`, {
      method: 'POST',
      body: {
        title: title.value.trim(),
        inspection_date: inspectionDate.value,
        building_ids: selectedBuildings.value
      }
    })

    await navigateTo(`/inspections/${inspection.id}`)
  } catch {
    submitError.value = 'Kunde inte skapa kontrollen. Kontrollera anslutningen och försök igen.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="page-shell">
    <nav class="top-nav">
      <NuxtLink to="/">Tillbaka</NuxtLink>
    </nav>

    <section class="content-card">
      <p class="eyebrow">Ny kontroll</p>
      <h1>Skapa brandskyddskontroll</h1>
      <p>
        Välj namn och datum. Alla aktiva byggnader är förvalda och kan väljas bort vid behov.
      </p>

      <form class="form-stack" @submit.prevent="createInspection">
        <label>
          Kontrollnamn
          <input v-model="title" type="text" aria-label="Kontrollnamn">
        </label>

        <label>
          Kontrolldatum
          <input v-model="inspectionDate" type="date" aria-label="Kontrolldatum">
        </label>

        <section>
          <h2>Byggnader</h2>
          <p v-if="pending">Hämtar byggnader…</p>
          <p v-else-if="error" class="error-text">Kunde inte hämta byggnader från API:t.</p>
          <div v-else class="building-grid">
            <button
              v-for="building in buildings"
              :key="building.id"
              type="button"
              class="building-toggle"
              :class="{ selected: selectedBuildings.includes(building.id) }"
              @click="toggleBuilding(building.id)"
            >
              <span>{{ building.name }}</span>
              <strong>{{ selectedBuildings.includes(building.id) ? 'Vald' : 'Välj' }}</strong>
            </button>
          </div>
          <p class="helper-text">Valda byggnader: {{ selectedBuildings.length }}</p>
        </section>

        <p v-if="submitError" class="error-text">{{ submitError }}</p>

        <button type="submit" class="button-link primary-action" :disabled="!canCreate">
          {{ isSubmitting ? 'Skapar kontroll…' : 'Skapa kontroll' }}
        </button>
      </form>
    </section>
  </main>
</template>
