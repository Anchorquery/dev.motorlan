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

const title = computed(() => {
  const parts = [
    motor.value.title,
    motor.value.acf.tipo_o_referencia,
    motor.value.acf.potencia ? `${motor.value.acf.potencia} kW` : null,
    motor.value.acf.velocidad ? `${motor.value.acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ')
})
</script>

<template>
  <VContainer
    v-if="motor"
    fluid
  >
    <VRow>
      <VCol cols="12">
        <div class="d-flex justify-space-between align-start mb-4">
          <h1 class="text-error">
            {{ title }}
          </h1>
          <div class="text-h1 text-error">
            {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
          </div>
        </div>
      </VCol>
    </VRow>
    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <ProductImage :motor="motor" />
      </VCol>
      <VCol
        cols="12"
        md="5"
      >
        <ProductDetails :motor="motor" />
      </VCol>
    </VRow>

    <div class="d-flex flex-wrap gap-6 my-8">
      <MotorInfo :motor="motor" />
      <ProductDocs :docs="docs" />
    </div>

    <RelatedProducts :current-id="motor.id" />
  </VContainer>

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
/* Ya no se necesita el CSS personalizado para el layout */
</style>
