<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
// Ajustes correctos de imports según la estructura del proyecto
// Ajuste final de imports según la estructura real del proyecto
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import type { Publicacion } from '@/interfaces/publicacion'
import { useApi } from '@/composables/useApi'

import { useUserStore } from '@/@core/stores/user'

const props = defineProps<{ publicacion: Publicacion }>()


// Estado de favorito
const isFavorite = ref(false)
const isLoadingFavorite = ref(false)

onMounted(async () => {
  if (userStore.getIsLoggedIn) {
    try {
      const { data: favorites, error } = await useApi('/wp-json/motorlan/v1/favorites').get().json()
      if (error.value)
        throw error.value
      if (favorites.value?.data && Array.isArray(favorites.value.data)) {
        isFavorite.value = favorites.value.data.some((f: any) => f.id === props.publicacion.id)
      }
    } catch (e) {
      console.error('Error cargando favoritos', e)
    }
  }
})
const sellerName = computed(() => props.publicacion.author?.name || 'N/A')
const sellerRating = computed(() => {
  const val = Number(props.publicacion.author?.acf?.calificacion)
  return Number.isFinite(val) ? val : null
})
const sellerSales = computed(() => {
  const val = Number(props.publicacion.author?.acf?.ventas)
  return Number.isFinite(val) ? val : null
})
const location = computed(() => {
  const { pais, provincia } = props.publicacion.acf
  if (pais && provincia)
    return `${pais} / ${provincia}`
  return pais || provincia || ''
})
const price = computed(() =>
  props.publicacion.acf.precio_de_venta
    ? `${props.publicacion.acf.precio_de_venta} €`
    : 'Consultar precio',
)

const negotiableLabel = computed(() => {
  const val = props.publicacion.acf.precio_negociable
  if (typeof val === 'string')
    return val.toLowerCase() === 'si' ? 'Negociable' : 'No negociable'
  return val ? 'Negociable' : 'No negociable'
})

const categories = computed(() => props.publicacion.categories.map((c: any) => c.name).join(', '))
const brand = computed(() =>
  typeof props.publicacion.acf.marca === 'object'
    // @ts-ignore
    ? (props.publicacion.acf.marca as any)?.name
    : props.publicacion.acf.name || ''
)

const productTitleUpper = computed(() => (props.publicacion.title ? props.publicacion.title.toUpperCase() : ''))

const toggleFavorite = async () => {
  if (!userStore.getIsLoggedIn || isLoadingFavorite.value) {
    if (!userStore.getIsLoggedIn) loginSnackbar.value = true
    return
  }

  isLoadingFavorite.value = true
  try {
    if (isFavorite.value) {
      const { error: deleteError } = await useApi(`/wp-json/motorlan/v1/favorites/${props.publicacion.id}`).delete().execute()
      if (deleteError.value)
        throw deleteError.value
      isFavorite.value = false
    }
    else {
      const { error: createError } = await useApi('/wp-json/motorlan/v1/favorites').post({ publicacion_id: props.publicacion.id }).execute()
      if (createError.value)
        throw createError.value
      isFavorite.value = true
    }
  } catch (e) {
    console.error('Error al cambiar favorito', e)
  } finally {
    isLoadingFavorite.value = false
  }
}

const shareSnackbar = ref(false)
const loginSnackbar = ref(false)
const isOfferDialogOpen = ref(false)
const offerAmount = ref<number | null>(null)
const offerMessage = ref('')
const offer = ref<any | null>(null)
const isSubmittingOffer = ref(false)

const userStore = useUserStore()
const isLoggedIn = computed(() => userStore.getIsLoggedIn)
const isOwner = computed(() => {
  if (!userStore.getUser?.id) return false
  return props.publicacion.author?.id === userStore.getUser.id
})

const isNegotiable = computed(() => {
  const val = props.publicacion.acf.precio_negociable
  if (typeof val === 'string') {
    const lowerVal = val.toLowerCase()
    return lowerVal === 'si' || lowerVal === 'yes'
  }
  return !!val
})

const share = () => {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({
      title: props.publicacion.title,
      url,
    })
  }
  else {
    navigator.clipboard.writeText(url)
    shareSnackbar.value = true
  }
}

const router = useRouter()
const isConfirmDialogOpen = ref(false)

const handlePurchase = async (confirmed: boolean) => {
  if (!confirmed)
    return

  try {
    const { data: res, error } = await useApi('/wp-json/motorlan/v1/purchases').post({ publicacion_id: props.publicacion.id }).json()
    if (error.value)
      throw error.value
    if (res.value)
      router.push(`/store/compra/${res.value.uuid}`)
  }
  catch (error) {
    console.error(error)
  }
}

const openOfferDialog = () => {
  if (!isLoggedIn.value) {
    loginSnackbar.value = true
    return
  }
  isOfferDialogOpen.value = true
}

const submitOffer = async () => {
  if (offerAmount.value === null || offerAmount.value >= Number(props.publicacion.acf.precio_de_venta || Infinity))
    return

  isSubmittingOffer.value = true
  try {
    const { data: res, error } = await useApi(`/wp-json/motorlan/v1/publicaciones/${props.publicacion.id}/offers`).post({
      amount: offerAmount.value,
      justification: offerMessage.value,
    }).json()
    if (error.value)
      throw error.value
    offer.value = res.value
    isOfferDialogOpen.value = false
  }
  catch (error) {
    console.error(error)
  }
  finally {
    isSubmittingOffer.value = false
  }
}

const removeOffer = async () => {
  if (!offer.value)
    return
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/offers/${offer.value.id}`).delete().execute()
    if (error.value)
      throw error.value
    offer.value = null
    offerAmount.value = null
    offerMessage.value = ''
  }
  catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <div class="product-details flex-grow-1">
    <div class="d-flex align-center gap-6 mb-4">
      <div
        v-if="isLoggedIn"
        class="d-flex align-center gap-2 pointer"
        @click="toggleFavorite"
      >
        <VProgressCircular
          v-if="isLoadingFavorite"
          indeterminate
          size="24"
          color="error"
        />
        <VIcon
          v-else
          :icon="isFavorite ? 'tabler-heart-filled' : 'tabler-heart'"
          color="error"
        />
        <span class="text-body-2 font-weight-medium">Favorito</span>
      </div>
      <div
        class="d-flex align-center gap-2 pointer"
        @click="share"
      >
        <VIcon
          icon="tabler-share"
          color="error"
        />
        <span class="text-body-2 font-weight-medium">Compartir</span>
      </div>
    </div>
    <VDivider class="mb-6" />

    <VCard class="mb-6 detail-card">
      <VCardText>
        <div class="mb-4">
          <h3 class="text-h6 mb-1">{{ productTitleUpper }}</h3>
          <div class="text-h5 font-weight-bold">{{ price }}</div>
        </div>
        <VRow class="motor-details" dense>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-arrows-left-right" class="mr-1" />
              <span>{{ negotiableLabel }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-cube" class="mr-1" />
              <span>{{ props.publicacion.tipo[0].name || 'N/A' }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-category" class="mr-1" />
              <span>{{ categories }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-tag" class="mr-1" />
              <span>{{ brand }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-map-pin" class="mr-1" />
              <span>{{ location }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-user" class="mr-1" />
              <span>{{ sellerName || 'N/A' }}</span>
              <VRating
                v-if="sellerRating !== null"
                class="ml-2"
                :model-value="sellerRating"
                readonly
                size="18"
                color="warning"
                density="compact"
              />
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-box" class="mr-1" />
              <span>Stock: {{ props.publicacion.acf.stock ?? 'N/A' }}</span>
            </div>
          </VCol>
          <VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-calendar-clock" class="mr-1" />
              <span>Alquiler: {{ props.publicacion.acf.posibilidad_de_alquiler || 'No' }}</span>
            </div>
          </VCol>
          <VCol v-if="sellerSales !== null" cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-chart-bar" class="mr-1" />
              <span>Ventas: {{ sellerSales }}</span>
            </div>
          </VCol>
        <VCol v-if="props.publicacion.acf.garantia_motorlan" cols="12" sm="6">
          <div class="detail-item d-flex align-center">
            <VIcon icon="tabler-shield-check" color="success" class="mr-1" />
            <span class="text-success">Garantía Motorlan</span>
          </div>
        </VCol>
      </VRow>
      </VCardText>
    </VCard>

    <div
      v-if="props.publicacion.acf.descripcion"
      class="contact-card pa-4 mb-6"
    >
      <p v-html="props.publicacion.acf.descripcion" />
    </div>

    <div class="d-flex flex-wrap gap-4 mb-6">
      <VBtn
        v-if="!isOwner"
        color="error"
        class="px-6 flex-grow-1 action-btn"
        @click="isConfirmDialogOpen = true"
      >
        Comprar
      </VBtn>
      <VBtn
        v-if="isNegotiable && !isOwner"
        variant="outlined"
        color="error"
        class="px-6 flex-grow-1 action-btn"
        @click="openOfferDialog"
      >
        {{ offer ? 'Editar oferta' : 'Hacer una oferta' }}
      </VBtn>
      <VBtn
        v-if="offer && isNegotiable && !isOwner"
        variant="text"
        color="error"
        class="px-6 flex-grow-1 action-btn"
        @click="removeOffer"
      >
        Quitar oferta
      </VBtn>
    </div>

    <VDialog
      v-model="isOfferDialogOpen"
      width="400"
    >
      <VCard>
        <VCardTitle>{{ offer ? 'Editar oferta' : 'Hacer una oferta' }}</VCardTitle>
        <VCardText>
          <VTextField
            v-model.number="offerAmount"
            label="Monto"
            type="number"
            :rules="[v => !v || v < Number(props.publicacion.acf.precio_de_venta) || 'Debe ser menor al precio']"
          />
          <VTextarea
            v-model="offerMessage"
            label="Justificación"
            class="mt-4"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="primary"
           :loading="isSubmittingOffer"
           @click="submitOffer"
         >
           Enviar
         </VBtn>
          <VBtn
            variant="text"
            @click="isOfferDialogOpen = false"
          >
            Cancelar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <ConfirmDialog
      v-model:is-dialog-visible="isConfirmDialogOpen"
      confirmation-question="¿Confirmar compra?"
      confirm-title="Compra realizada"
      confirm-msg="Redirigiendo..."
      cancel-title="Cancelado"
      cancel-msg="Operación cancelada"
      @confirm="handlePurchase"
    />

    <VSnackbar
      v-model="shareSnackbar"
      color="success"
      location="top right"
    >
      Enlace copiado al portapapeles
    </VSnackbar>
    <VSnackbar
      v-model="loginSnackbar"
      color="error"
      location="top right"
    >
      Debes registrarte para hacer una oferta
    </VSnackbar>
  </div>
</template>

<style scoped>
.action-btn {
  border-radius: 4px;
}
.pointer {
  cursor: pointer;
}
.contact-card {
  border: 1px solid #E6E6E6;
  border-radius: 8px;
}

.detail-card {
  border: 1px solid #E6E6E6;
  border-radius: 8px;
}

.motor-details {
  margin: 0;
}

.detail-item {
  margin-bottom: 0.5rem;
}
</style>
