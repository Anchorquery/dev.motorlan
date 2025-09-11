<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const isLoading = ref(false)
const stats = ref({
  sales: 0,
  purchases: 0,
  rating: 0,
  reviews: [],
})

const fetchStats = async () => {
  isLoading.value = true
  try {
    const { data, error } = await api('/stats')
    if (error.value) throw error.value
    if (data.value)
      stats.value = data.value
  }
  catch (error) {
    console.error('Error fetching stats:', error)
  }
  finally {
    isLoading.value = false
  }
}

onMounted(fetchStats)
</script>

<template>
  <VRow>
    <!-- Estadísticas Generales -->
    <VCol cols="12" md="4">
      <VCard :loading="isLoading">
        <VCardTitle>Resumen</VCardTitle>
        <VCardText class="text-center">
          <div class="mb-4">
            <h3 class="text-h3">{{ stats.sales }}</h3>
            <span>Ventas</span>
          </div>
          <div>
            <h3 class="text-h3">{{ stats.purchases }}</h3>
            <span>Compras</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Calificación -->
    <VCol cols="12" md="4">
      <VCard :loading="isLoading">
        <VCardTitle>Calificación</VCardTitle>
        <VCardText class="d-flex justify-center align-center">
          <VProgressCircular
            :model-value="stats.rating * 20"
            :size="150"
            :width="15"
            color="primary"
          >
            <span class="text-h4">{{ stats.rating }}</span>
          </VProgressCircular>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Opiniones -->
    <VCol cols="12" md="4">
      <VCard :loading="isLoading">
        <VCardTitle>Opiniones Recientes</VCardTitle>
        <VCardText>
          <VList lines="two">
            <VListItem
              v-for="review in stats.reviews"
              :key="review.id"
              :title="review.author"
              :subtitle="review.text"
            >
              <template #append>
                <div class="d-flex align-center">
                  <span class="mr-1">{{ review.rating }}</span>
                  <VIcon icon="tabler-star" color="amber" />
                </div>
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
