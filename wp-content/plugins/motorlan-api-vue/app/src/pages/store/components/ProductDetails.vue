<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
// Ajustes correctos de imports según la estructura del proyecto
// Ajuste final de imports según la estructura real del proyecto
import type { Publicacion } from '@/interfaces/publicacion'
import { useApi } from '@/composables/useApi'
import { useUserStore } from '@/@core/stores/user'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{ 
  publicacion: Publicacion; 
  disableActions?: boolean;
  isPreview?: boolean;
}>()
const emit = defineEmits(['open-chat'])

const brand = computed(() => (props.publicacion as any).marca_name || '-')


// Estado de favorito
const isFavorite = ref(false)
const isLoadingFavorite = ref(false)

const userStore = useUserStore()
const bootstrapUserId = computed(() => (window as any)?.wpData?.user_data?.user?.id)
const isLoggedIn = computed(() =>
  userStore.getIsLoggedIn
  || Boolean(userStore.getUser?.id)
  || Boolean(userStore.user?.id)
  || Boolean(bootstrapUserId.value),
)

onMounted(async () => {
  
  if (!isLoggedIn.value)
    await userStore.fetchUserSession()

  if (isLoggedIn.value && !props.isPreview) {
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
const sellerName = computed(() => {
  const auth = props.publicacion.author
  if (!auth) return 'Vendedor'
  
  const firstName = auth.first_name || ''
  const lastName = auth.last_name || ''
  const fullName = `${firstName} ${lastName}`.trim()

  // Only return if we have at least one name part, otherwise default to 'Vendedor'
  // Avoid showing emails or technical names (nickname/user_login)
  return fullName || 'Vendedor'
})

const getInitials = (value: string): string => {
  if (!value || value === 'Vendedor') return 'V'
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('')
}
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
const negotiableLabel = computed(() => {
  const val = props.publicacion.acf.precio_negociable
  if (typeof val === 'string')
    return val.toLowerCase() === 'si' ? 'Negociable' : 'No negociable'
  return val ? 'Negociable' : 'No negociable'
})

const categories = computed(() => props.publicacion.categories.map((c: any) => c.name).join(', '))

const productTitleUpper = computed(() => (props.publicacion.title ? props.publicacion.title.toUpperCase() : ''))

const toggleFavorite = async () => {
  if (isLoadingFavorite.value)
    return

  isLoadingFavorite.value = true
  try {
    if (isFavorite.value) {
      const deleteRequest = useApi(`/wp-json/motorlan/v1/favorites/${props.publicacion.id}`, { immediate: false }).delete()
      await deleteRequest.execute()
      if (deleteRequest.error.value)
        throw deleteRequest.error.value
      isFavorite.value = false
    }
    else {
      const createRequest = useApi('/wp-json/motorlan/v1/favorites', { immediate: false }).post({ publicacion_id: props.publicacion.id })
      await createRequest.execute()
      if (createRequest.error.value)
        throw createRequest.error.value
      isFavorite.value = true
    }
  } catch (e) {
    console.error('Error al cambiar favorito', e)
  } finally {
    isLoadingFavorite.value = false
  }
}

const shareSnackbar = ref(false)
const isOfferDialogOpen = ref(false)

const offerAmount = ref<number | null>(null)
const offerMessage = ref('')
const offer = ref<any | null>(null)
const isSubmittingOffer = ref(false)


const isOwner = computed(() => {
  if (!userStore.getUser?.id) return false
  return props.publicacion.author?.id === userStore.getUser.id
})
const actionsAvailable = computed(() => !props.disableActions && !isOwner.value && !props.isPreview)

const isNegotiable = computed(() => {
  const val = props.publicacion?.acf?.precio_negociable

  if (val === undefined || val === null)
    return false

  if (typeof val === 'string') {
    const lowerVal = val.toLowerCase().trim()
    
    return lowerVal === 'si' || lowerVal === 'yes' || lowerVal === 'true'
  }

  if (Array.isArray(val))
    return val.length > 0 && (val[0] === 'si' || val[0] === true)

  return val === true
})

import { useShare, useClipboard } from '@vueuse/core'

const isShareModalOpen = ref(false)
const { copy, copied } = useClipboard()

const shareOptions = computed(() => {
  const url = window.location.href
  const text = `Echa un vistazo a ${props.publicacion.title} en Motorlan`
  
  return [
    {
      name: 'WhatsApp',
      icon: 'tabler-brand-whatsapp',
      color: '#25D366',
      action: () => {
        window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank')
      }
    },
    {
      name: 'Telegram',
      icon: 'tabler-brand-telegram',
      color: '#0088cc',
      action: () => {
        window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank')
      }
    },
    {
      name: 'Email',
      icon: 'tabler-mail',
      color: 'primary',
      action: () => {
        window.open(`mailto:?subject=${encodeURIComponent(props.publicacion.title)}&body=${encodeURIComponent(text + ' ' + url)}`, '_self')
      }
    },
    {
      name: 'Copiar enlace',
      icon: 'tabler-copy',
      color: 'secondary',
      action: () => {
        copy(url)
        shareSnackbar.value = true
        isShareModalOpen.value = false
      }
    }
  ]
})


const share = () => {
  isShareModalOpen.value = true
}

const router = useRouter()

const openChatModal = () => {
  emit('open-chat')
}

const openOfferDialog = () => {
  isOfferDialogOpen.value = true
}

const submitOffer = async () => {
  if (offerAmount.value === null || offerAmount.value <= 0)
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
        v-if="isLoggedIn && !isOwner"
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
    <VDivider class="mb-6 opacity-20" />

    <VCard class="mb-6 detail-card product-detail-card-enhanced pa-4">
      <VCardText>
        <div class="mb-4">
          <h3 class="text-h6 mb-1">{{ productTitleUpper }}</h3>
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
              <span class="text-truncate" style="max-width: 150px;" :title="sellerName">{{ sellerName }}</span>
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
      <template v-if="actionsAvailable">
        <VBtn
          color="error"
          class="px-6 flex-grow-1 action-btn"
          @click="openChatModal"
        >
          Consultar precio
        </VBtn>
        <VBtn
          v-if="isNegotiable"
          variant="outlined"
          color="error"
          class="px-6 flex-grow-1 action-btn"
          @click="openOfferDialog"
        >
          {{ offer ? 'Editar oferta' : 'Hacer una oferta' }}
        </VBtn>
        <VBtn
          v-if="offer && isNegotiable"
          variant="text"
          color="error"
          class="px-6 flex-grow-1 action-btn"
          @click="removeOffer"
        >
          Quitar oferta
        </VBtn>
      </template>
    </div>

    <VDialog
      v-model="isOfferDialogOpen"
      width="460"
      persistent
    >
      <VCard class="offer-modal">
        <VCardTitle class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-gavel" color="error" />
            <span>{{ offer ? 'Editar oferta' : 'Hacer una oferta' }}</span>
          </div>
          <VBtn icon="tabler-x" variant="text" @click="isOfferDialogOpen = false" />
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 mb-3">
            Tu oferta quedará pendiente hasta que el vendedor la acepte. Si la acepta,
            tendrás 24 horas para completar la compra.
          </p>
          <VTextField
            v-model.number="offerAmount"
            label="Precio de la oferta"
            type="number"
            class="mt-4"
            :rules="[
              v => !!v || 'El monto es requerido',
              v => v > 0 || 'El monto debe ser mayor a 0'
            ]"
          />
          <VTextarea
            v-model="offerMessage"
            label="Mensaje al vendedor"
            class="mt-4"
            rows="3"
          />
          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            No se cobrará nada hasta que el vendedor acepte tu oferta.
          </VAlert>
        </VCardText>
        <VCardActions class="px-6 pb-6">
          <VBtn
            color="error"
            variant="flat"
            :loading="isSubmittingOffer"
            class="flex-grow-1"
            @click="submitOffer"
          >
            Enviar oferta
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isShareModalOpen"
      width="400"
    >
      <VCard class="share-modal">
        <VCardTitle class="d-flex align-center justify-space-between pt-4 pl-4 pr-4">
          <span class="text-h6">Compartir publicación</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isShareModalOpen = false" />
        </VCardTitle>
        <VCardText class="pb-6">
          <div class="d-flex flex-column gap-3 mt-2">
            <VBtn
              v-for="option in shareOptions"
              :key="option.name"
              block
              variant="outlined"
              :color="option.color"
              class="justify-start px-4"
              style="height: 48px;"
              @click="option.action"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="24" :color="option.color" class="mr-2" />
              </template>
              {{ option.name }}
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>


    <VSnackbar
      v-model="shareSnackbar"
      color="success"
      location="top end"
    >
      Enlace copiado al portapapeles
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
.offer-modal {
  border-radius: 16px;
}
</style>
