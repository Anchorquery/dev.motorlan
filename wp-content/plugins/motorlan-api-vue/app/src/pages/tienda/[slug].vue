<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductImage from './components/ProductImage.vue'
import ProductDetails from './components/ProductDetails.vue'
import MotorInfo from './components/MotorInfo.vue'
import ProductDocs from './components/ProductDocs.vue'
import RelatedProducts from './components/RelatedProducts.vue'
import type { Motor } from '@/interfaces/motor'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const slug = route.params.slug as string

const { data, isFetching } = await useApi<any>(
  createUrl(`/wp-json/motorlan/v1/motors/${slug}`),
).get().json()

const motor = computed(() => data.value?.data as Motor | undefined)

const docs = computed(() => {
  const raw = motor.value?.acf?.documentacion || motor.value?.acf?.documentacion_adjunta
  if (!raw)
    return []
  const arr = Array.isArray(raw) ? raw : [raw]

  return arr
    .filter((d: any) => d && d.url)
    .map((d: any) => ({ title: d.title || 'Documento', url: d.url }))
})
</script>

<template>
  <div
    v-if="motor"
    class="motor-detail"
  >
    <div class="top-section mb-8">
      <ProductImage :motor="motor" />
      <ProductDetails :motor="motor" />
    </div>
    <div class="d-flex flex-wrap gap-6 mb-8">
      <MotorInfo :motor="motor" />
      <ProductDocs :docs="docs" />
    </div>
    <RelatedProducts :current-id="motor.id" />
  </div>
  <div
    v-else-if="isFetching"
    class="text-center pa-12"
  >
    <VProgressCircular
      indeterminate
      size="64"
    />
  </div>
  <VCard
    v-else
    class="pa-8 text-center"
  >
    <VCardText>Motor no encontrado</VCardText>
  </VCard>
</template>

<style scoped>
.motor-detail {
  max-width: 1200px;
  margin-inline: auto;
}

.top-section {
  display: flex;
  gap: 24px;
}

.top-section > * {
  flex: 1 1 50%;
}

@media (max-width: 960px) {
  .top-section {
    flex-direction: column;
  }
}
</style>
