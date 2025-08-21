<script setup lang="ts">
import { computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ currentId: number }>()

const { data } = await useApi<any>(
  createUrl('/wp-json/motorlan/v1/motors', { query: { per_page: 4 } })
).get().json()

const products = computed(() => (data.value?.data || []).filter((m: Motor) => m.id !== props.currentId))
</script>

<template>
  <div class="related-products" v-if="products.length">
    <h3 class="text-error mb-4">Productos relacionados</h3>
    <VRow>
      <VCol v-for="motor in products" :key="motor.id" cols="12" sm="6" md="3">
        <div class="motor-card pa-4">
          <div class="motor-image mb-4">
            <img :src="motor.imagen_destacada?.url || '/placeholder.png'" alt="" />
          </div>
          <div class="text-error text-body-1 mb-4">{{ motor.title }}</div>
          <VBtn color="error" class="rounded-pill px-6" :to="'/tienda/' + motor.id">+ INFO</VBtn>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.related-products .motor-card {
  background: #fff;
  border-radius: 16px;
}
.related-products .motor-image {
  height: 135px;
  border-radius: 8px;
  background: #EEF1F4;
  display: flex;
  align-items: center;
  justify-content: center;
}
.related-products .motor-image img {
  max-width: 100%;
  max-height: 100%;
}
</style>
