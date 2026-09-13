<template>
  <main class="app-shell">
    <section class="hero-card">
      <p class="eyebrow">BRF VIGELSJÖHÖJDEN</p>
      <h1>Brandskyddskontroller</h1>
      <p>
        SBA-appen är igång. Skapa en ny kvartalskontroll eller öppna listan
        med tidigare kontroller.
      </p>
      <div class="actions">
        <NuxtLink class="button-link" to="/inspections/new">Ny kontroll</NuxtLink>
        <NuxtLink class="button-link secondary" to="/inspections">Tidigare kontroller</NuxtLink>
      </div>
    </section>
  </main>
</template>
