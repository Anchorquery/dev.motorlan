<script setup lang="ts">
import { computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{ currentId: number }>()

const { data } = await useApi<any>(
  createUrl('/wp-json/motorlan/v1/publicaciones', { query: { per_page: 4 } })
).get().json()

const products = computed(() => (data.value?.data || []).filter((m: Publicacion) => m.id !== props.currentId))

const formatProductTitle = (publication: Publicacion) => {
  const acf = publication?.acf || {}
  const parts = [
    publication?.title,
    acf?.tipo_o_referencia,
    acf?.potencia ? `${acf.potencia} kW` : null,
    acf?.velocidad ? `${acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ').toUpperCase()
}
</script>

<template>
  <div class="related-products" v-if="products.length">
    <h3 class="mb-4">Productos relacionados</h3>
    <VRow>
      <VCol v-for="publicacion in products" :key="publicacion.id" cols="12" sm="6" md="3">
        <div class="motor-card pa-4">
          <div class="motor-image mb-4">
            <img :src="publicacion.imagen_destacada?.url || '/placeholder.png'" alt="" />
          </div>
          <div class="text-body-1 mb-4">{{ formatProductTitle(publicacion) }}</div>
          <VBtn color="error" class="rounded-pill px-6" :to="'/store/' + publicacion.slug">+ INFO</VBtn>
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
