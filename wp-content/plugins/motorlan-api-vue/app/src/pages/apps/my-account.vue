<script setup lang="ts">
const route = useRoute()

const tabs = [
  { title: 'Compras', to: '/apps/my-account/purchases' },
  { title: 'Preguntas', to: '/apps/my-account/questions' },
  { title: 'Opiniones', to: '/apps/my-account/opinions' },
  { title: 'Favoritos', to: '/apps/my-account/favorites' },
]
</script>

<template>
  <div>
    <VTabs
      :model-value="route.path"
      class="v-tabs-pill"
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        :value="tab.to"
      >
        {{ tab.title }}
      </VTab>
    </VTabs>

    <VWindow
      :model-value="route.path"
      class="mt-6"
    >
      <VWindowItem
        v-for="tab in tabs"
        :key="tab.to"
        :value="tab.to"
      >
        <NuxtPage />
      </VWindowItem>
    </VWindow>
  </div>
</template>
