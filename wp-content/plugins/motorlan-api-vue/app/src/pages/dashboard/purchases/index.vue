<script setup lang="ts">
const route = useRoute()
const router = useRouter()

const tabs = [
  { title: 'Compras', to: { name: 'dashboard-purchases-purchases' } },
  { title: 'Preguntas', to: { name: 'dashboard-purchases-questions' } },
  { title: 'Opiniones', to: { name: 'dashboard-purchases-opinions' } },
  { title: 'Favoritos', to: { name: 'dashboard-purchases-favorites' } },
]

const activeTab = computed({
  get: () => tabs.findIndex(tab => tab.to.name === route.name),
  set: val => {
    router.push(tabs[val].to)
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
