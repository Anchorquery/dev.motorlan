<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import type { Motor } from '@/interfaces/motor'
import ProductImage from './components/ProductImage.vue'
import ProductDetails from './components/ProductDetails.vue'
import ProductDocs from './components/ProductDocs.vue'
import RelatedProducts from './components/RelatedProducts.vue'

const route = useRoute()
const slug = route.params.slug as string

const { data, isFetching } = await useApi<any>(
  createUrl('/wp-json/wp/v2/motors', { query: { slug } }),
).get().json()

const motor = computed(() => (data.value?.[0] as Motor | undefined))
</script>

<template>
  <div v-if="motor" class="motor-detail">
    <div class="d-flex flex-wrap gap-6 mb-8">
      <ProductImage :motor="motor" />
      <ProductDetails :motor="motor" />
    </div>
    <ProductDocs :docs="motor.acf?.documentacion" class="mb-8" />
    <RelatedProducts :current-id="motor.id" />
  </div>
  <div v-else-if="isFetching" class="text-center pa-12">
    <VProgressCircular indeterminate size="64" />
  </div>
  <VCard v-else class="pa-8 text-center">
    <VCardText>Motor no encontrado</VCardText>
  </VCard>
</template>

<style scoped>
.motor-detail {
  max-width: 1200px;
  margin-inline: auto;
}
</style>
