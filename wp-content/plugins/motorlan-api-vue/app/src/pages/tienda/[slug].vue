<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ProductImage from './components/ProductImage.vue'
import ProductDetails from './components/ProductDetails.vue'
import PublicacionInfo from './components/PublicacionInfo.vue'
import ProductDocs from './components/ProductDocs.vue'
import RelatedProducts from './components/RelatedProducts.vue'
import PublicacionQuestions from './components/PublicacionQuestions.vue'
import type { Publicacion } from '@/interfaces/publicacion'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const slug = route.params.slug as string

const { data, isFetching } = await useApi<any>(
  createUrl(`/wp-json/motorlan/v1/publicaciones/${slug}`),
).get().json()

const publicacion = computed(() => data.value?.data as Publicacion | undefined)

const docs = computed(() => {
  const raw = publicacion.value?.acf?.documentacion || publicacion.value?.acf?.documentacion_adjunta
  if (!raw)
    return []
  const arr = Array.isArray(raw) ? raw : [raw]

  return arr
    .filter((d: any) => d && d.url)
    .map((d: any) => ({ title: d.title || 'Documento', url: d.url }))
})

const title = computed(() => {
  const parts = [
    publicacion.value.title,
    publicacion.value.acf.tipo_o_referencia,
    publicacion.value.acf.potencia ? `${publicacion.value.acf.potencia} kW` : null,
    publicacion.value.acf.velocidad ? `${publicacion.value.acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ')
})
</script>

<template>
  <VContainer
    v-if="publicacion"
    fluid
  >
    <VRow>
      <VCol cols="12">
        <div class="d-flex justify-space-between mb-4 title-price">
          <h1 class="text-h4 mb-0">
            {{ title }}
          </h1>
          <div class="text-h4 text-error font-weight-bold">
            {{ publicacion.acf.precio_de_venta ? `${publicacion.acf.precio_de_venta}€` : 'Consultar precio' }}
          </div>
        </div>
      </VCol>
    </VRow>
    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <ProductImage :publicacion="publicacion" />
      </VCol>
      <VCol
        cols="12"
        md="5"
      >
        <ProductDetails :publicacion="publicacion" />
      </VCol>
    </VRow>

    <div class="d-flex flex-wrap gap-6 my-8">
      <PublicacionInfo :publicacion="publicacion" />
      <ProductDocs :docs="docs" />
    </div>

    <RelatedProducts :current-id="publicacion.id" />
    <PublicacionQuestions :publicacion-id="publicacion.id" />
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
    <VCardText>Publicación no encontrada</VCardText>
  </VCard>
</template>

<style scoped>
.title-price {
  align-items: baseline;
}
</style>
