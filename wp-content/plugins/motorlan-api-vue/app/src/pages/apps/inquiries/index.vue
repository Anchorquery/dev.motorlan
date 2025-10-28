<script setup lang="ts">
import { onMounted, ref } from 'vue'

const isLoading = ref(false)
const loadError = ref<string | null>(null)
const chats = ref<any[]>([])

onMounted(async () => {
  // Placeholder: backend route pending
  isLoading.value = false
  loadError.value = null
  chats.value = []
})
</script>

<template>
  <VContainer class="py-6">
    <VRow>
      <VCol cols="12">
        <VCard class="pa-6">
          <VCardTitle>Interacciones de interés (pre-compra)</VCardTitle>
          <VCardText>
            <VAlert type="info" variant="tonal" class="mb-4">
              Esta sección mostrará los chats iniciados por compradores interesados antes de comprar.
            </VAlert>
            <div v-if="isLoading" class="d-flex justify-center">
              <VProgressCircular color="primary" indeterminate size="32" width="3" />
            </div>
            <VAlert v-else-if="loadError" type="error" variant="tonal">{{ loadError }}</VAlert>
            <div v-else>
              <p class="text-medium-emphasis">Aún no hay conversaciones para mostrar.</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
  
</template>

<style scoped>
</style>

