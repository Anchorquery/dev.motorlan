<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/@core/stores/user'
import ProductImage from './components/ProductImage.vue'
import ProductDetails from './components/ProductDetails.vue'
import PublicacionInfo from './components/PublicacionInfo.vue'
import ProductDocs from './components/ProductDocs.vue'
import RelatedProducts from './components/RelatedProducts.vue'
import OfferModal from './components/OfferModal.vue'
import type { Publicacion } from '@/interfaces/publicacion'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const slug = route.params.slug as string
const userStore = useUserStore()

const { data, isFetching } = await useApi<any>(
  createUrl(`/wp-json/motorlan/v1/publicaciones/${slug}`),
).get().json()

const isOfferModalVisible = ref(false)

const isOwner = computed(() => {
  if (!userStore.user || !publicacion.value)
    return false

  return Number(userStore.user.id) === Number(publicacion.value.post_author)
})

const publicacion = computed(() => {
  if (!data.value?.data)
    return undefined

  return {
    ...data.value.data,
    imagen_destacada: data.value.imagen_destacada,
  } as Publicacion
})

const docs = computed(() => {
  const raw = publicacion.value?.acf?.documentacion_adicional
  if (!raw || !Array.isArray(raw))
    return []

  return raw
    .filter((d: any) => d && d.archivo && d.archivo.url)
    .map((d: any) => ({ title: d.nombre || d.archivo.title || 'Documento', url: d.archivo.url }))
})

const title = computed(() => {
  const parts = [
    publicacion.value.title,
    publicacion.value.acf.tipo_o_referencia,
    publicacion.value.acf.potencia ? `${publicacion.value.acf.potencia} kW` : null,
    publicacion.value.acf.velocidad ? `${publicacion.value.acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ').toUpperCase()
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
        <div
          v-if="publicacion.acf.precio_negociable && !isOwner"
          class="d-flex gap-4 mt-4"
        >
          <VBtn
            variant="tonal"
            @click="isOfferModalVisible = true"
          >
            Hacer una oferta
          </VVBtn>
          <VBtn>Comprar</VBtn>
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
    <OfferModal
      v-if="isOfferModalVisible"
      :publicacion-id="publicacion.id"
      @close="isOfferModalVisible = false"
    />
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
