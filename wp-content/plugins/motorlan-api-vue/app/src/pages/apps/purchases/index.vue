<script setup lang="ts">
const route = useRoute()

const tabs = [
  { title: 'Compras', to: { name: 'apps-purchases-purchases' } },
  { title: 'Preguntas', to: { name: 'apps-purchases-questions' } },
  { title: 'Opiniones', to: { name: 'apps-purchases-opinions' } },
  { title: 'Favoritos', to: { name: 'apps-purchases-favorites' } },
]

const activeTab = computed({
  get: () => tabs.findIndex(tab => tab.to.name === route.name),
  set: val => {
    navigateTo(tabs[val].to)
  },
})
</script>

<template>
  <div>
    <VTabs
      v-model="activeTab"
      class="v-tabs-pill"
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.to.name"
        :to="tab.to"
      >
        {{ tab.title }}
      </VTab>
    </VTabs>

    <VWindow
      v-model="activeTab"
      class="mt-6"
      :touch="false"
    >
      <VWindowItem
        v-for="tab in tabs"
        :key="tab.to.name"
      >
        <NuxtPage />
      </VWindowItem>
    </VWindow>
  </div>
</template>
