<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ProductImage from '@/pages/store/components/ProductImage.vue'
import ProductDetails from '@/pages/store/components/ProductDetails.vue'
import PublicacionInfo from '@/pages/store/components/PublicacionInfo.vue'
import ProductDocs from '@/pages/store/components/ProductDocs.vue'
import RelatedProducts from '@/pages/store/components/RelatedProducts.vue'
import OfferModal from '@/pages/store/components/OfferModal.vue'
import ChatModal from '@/pages/store/components/ChatModal.vue'
import LoginModal from '@/pages/store/components/LoginModal.vue'
import type { Publicacion } from '@/interfaces/publicacion'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/utils/formatCurrency'
import { useUserStore } from '@/@core/stores/user'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute()
const slug = route.params.slug as string

const { data, isFetching, execute } = useApi<any>(
  createUrl(`/wp-json/motorlan/v1/publicaciones/${slug}`),
  { immediate: false }
)
  .get()
  .json()

onMounted(execute)

const isOfferModalVisible = ref(false)

const isChatModalVisible = ref(false)
const isLoginModalVisible = ref(false)
const chatRoomKeyFromQuery = computed(() => {
  const raw = (route.query.room_key as string) || ''
  return raw && raw.trim().length ? raw : null
})

const publicacion = computed(() => {
  if (!data.value?.data) return undefined

  return {
    ...data.value.data,
    imagen_destacada: data.value.imagen_destacada,
  } as Publicacion
})

const docs = computed(() => {
  const raw = publicacion.value?.acf?.documentacion_adicional
  if (!raw || !Array.isArray(raw)) return []

  return raw
    .filter((d: any) => d && d.archivo && d.archivo.url)
    .map((d: any) => ({
      title: d.nombre || d.archivo.title || 'Documento',
      url: d.archivo.url,
    }))
})

const title = computed(() => {
  if (!publicacion.value) return ''

  const parts = [
    publicacion.value.title,
    publicacion.value.acf.tipo_o_referencia,
    publicacion.value.acf.potencia
      ? `${publicacion.value.acf.potencia} kW`
      : null,
    publicacion.value.acf.velocidad
      ? `${publicacion.value.acf.velocidad} rpm`
      : null,
  ].filter(Boolean)

  return parts.join(' ').toUpperCase()
})

const priceLabel = computed(() => formatCurrency(publicacion.value?.acf?.precio_de_venta) ?? 'Consultar precio')

onMounted(() => {
  if (route.query.open_chat === '1')
    isChatModalVisible.value = true
})

const userStore = useUserStore()
const isLoggedIn = computed(() => !!userStore.getUser?.id)

onMounted(() => {
  if (!isLoggedIn.value) {
    userStore.fetchUserSession()
  }
})
</script>

<template>
  <VContainer v-if="publicacion" fluid>
    <VRow>
      <VCol cols="12">
        <div class="d-flex justify-space-between mb-4 title-price">
          <h1 class="text-h4 mb-0">
            {{ title }}
          </h1>
          <div class="text-h4 text-error font-weight-bold">
            {{ priceLabel }}
          </div>
        </div>
      </VCol>
    </VRow>
    <VRow>
      <VCol cols="12" md="7">
        <ProductImage :publicacion="publicacion" />
        <VRow class="mt-6">
          <VCol cols="12" md="6">
            <PublicacionInfo :publicacion="publicacion" />
          </VCol>
          <VCol cols="12" md="6">
            <ProductDocs :docs="docs" />
          </VCol>
        </VRow>
      </VCol>
      <VCol cols="12" md="5">
        <ProductDetails 
          :publicacion="publicacion" 
          @login="isLoginModalVisible = true"
        />
        <div class="d-flex align-center mb-4" v-if="publicacion.author">
          <VAvatar size="48" class="mr-4">
            <VImg :src="publicacion.author.avatar" :alt="publicacion.author.name || 'Vendedor'" />
          </VAvatar>
          <div>
            <p class="font-weight-bold mb-0">{{ publicacion.author.name || 'Vendedor' }}</p>
            <p class="text-caption mb-0">
              {{ Number(publicacion.author.acf?.ventas || 0) }} ventas |
              {{ Number(publicacion.author.acf?.calificacion || 0) }} valoraciones
            </p>
          </div>
          <VBtn class="ml-auto" color="primary" variant="outlined" @click="isChatModalVisible = true">
            Abrir chat
          </VBtn>
        </div>
      </VCol>
    </VRow>




    <RelatedProducts :current-id="publicacion.id" />
    <OfferModal
      v-if="isOfferModalVisible"
      :publicacion-id="publicacion.id"
      @close="isOfferModalVisible = false"
    />
    <ChatModal
      v-if="isChatModalVisible"
      :publicacion="publicacion"
      :room-key="chatRoomKeyFromQuery"
      @close="isChatModalVisible = false"
    />
    <LoginModal
      v-model:isDialogVisible="isLoginModalVisible"
    />
  </VContainer>

  <div v-else-if="isFetching" class="text-center pa-12">
    <VProgressCircular indeterminate size="64" />
  </div>
  <VCard v-else class="pa-8 text-center">
    <VCardText>Publicación no encontrada</VCardText>
  </VCard>
</template>

<style scoped>
.title-price {
  align-items: baseline;
}
.public-store-cta-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}
</style>
