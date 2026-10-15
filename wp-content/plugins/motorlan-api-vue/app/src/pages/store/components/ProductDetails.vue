<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
// Ajustes correctos de imports según la estructura del proyecto
// Ajuste final de imports según la estructura real del proyecto
import type { Publicacion } from '@/interfaces/publicacion'
import { assertApiOk, useApi } from '@/composables/useApi'
import { useUserStore } from '@/@core/stores/user'
import { createUrl } from '@/@core/composable/createUrl'
import { useSanitize } from '@/composables/useSanitize'
import { useCountries } from '@/composables/useCountries'
import { useToast } from '@/composables/useToast'
import { uiSlugForTipo } from '@/utils/tipoMapping'

const props = defineProps<{ 
  publicacion: Publicacion; 
  disableActions?: boolean;
  isPreview?: boolean;
}>()
const emit = defineEmits(['open-chat'])
const { sanitize } = useSanitize()
const { getCountryName, fetchCountries } = useCountries()
const { t, locale } = useI18n()
const { showToast } = useToast()

const brand = computed(() => props.publicacion.marca_name || '-')


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
  await fetchCountries()

  if (!isLoggedIn.value)
    await userStore.fetchUserSession()

  if (isLoggedIn.value && !props.isPreview) {
    try {
      // Solo IDs: antes se descargaban todos los favoritos con todos sus datos
      const { data: favorites, error } = await useApi('/wp-json/motorlan/v1/favorites?fields=ids').get().json()
      if (error.value)
        throw error.value
      if (favorites.value?.data && Array.isArray(favorites.value.data)) {
        isFavorite.value = favorites.value.data.some((f: { id: number }) => Number(f.id) === Number(props.publicacion.id))
      }
    } catch (e) {
      console.error('Error cargando favoritos', e)
    }
  }
})
const sellerName = computed(() => {
  const auth = props.publicacion.author
  if (!auth) return t('product_details.seller_default')

  const firstName = auth.first_name || ''
  const lastName = auth.last_name || ''
  const fullName = `${firstName} ${lastName}`.trim()

  return fullName || t('product_details.seller_default')
})

const getInitials = (value: string): string => {
  if (!value || value === t('product_details.seller_default')) return 'V'
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('')
}
// calificacion es null si el vendedor aún no tiene valoraciones (Number(null) = 0 pintaba 0 estrellas)
const sellerRating = computed(() => {
  const raw = (props.publicacion.author?.acf as any)?.calificacion
  if (raw === null || raw === undefined || raw === '')
    return null
  const val = Number(raw)
  return Number.isFinite(val) && val > 0 ? val : null
})
const sellerSales = computed(() => {
  const raw = (props.publicacion.author?.acf as any)?.ventas
  if (raw === null || raw === undefined || raw === '')
    return null
  const val = Number(raw)
  return Number.isFinite(val) ? val : null
})
const location = computed(() => {
  const { pais, provincia } = props.publicacion.acf
  const countryName = getCountryName(pais)
  if (countryName && provincia)
    return `${countryName} / ${provincia}`
  return countryName || provincia || ''
})
const isConsultPrice = computed(() => isNegotiable.value)

const showPrice = computed(() => {
  // Mostrar precio si hay precio Y no esta marcado "Consultar precio"
  // O si es el owner (siempre ve el precio como referencia)
  if (isConsultPrice.value && !isOwner.value) return false
  return hasPrice.value
})

const hasPrice = computed(() => {
  const price = props.publicacion.acf?.precio_de_venta
  
  if (price === null || price === undefined) return false

  return Number(price) > 0
})

// Muestra el tipo de publicación con la alimentación incluida para motores:
// Motor AC / Motor DC / Regulador / Otros.
const tipoDisplay = computed(() => {
  const tipos = (props.publicacion.tipo || []) as { name: string; slug?: string }[]
  if (!tipos.length) return ''

  const slug = tipos[0].slug || ''
  const ui = uiSlugForTipo(slug, props.publicacion.acf?.tipo_de_alimentacion)

  switch (ui) {
    case 'motor-ac': return t('select_publication_type.motor_ac_title')
    case 'motor-dc': return t('select_publication_type.motor_dc_title')
    case 'regulador': return t('select_publication_type.regulator_title')
    case 'otro-repuesto': return t('select_publication_type.other_spare_part_title')
    default: return tipos[0].name || ''
  }
})

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
    showToast(t('product_details.favorite_error', 'No se pudo actualizar el favorito. Inténtalo de nuevo.'), 'error')
  } finally {
    isLoadingFavorite.value = false
  }
}

const shareSnackbar = ref(false)
const isOfferDialogOpen = ref(false)

const offerAmount = ref<number | null>(null)
const offerMessage = ref('')
const offer = ref<null | { id: number }>(null)
const offerError = ref('')
const isSubmittingOffer = ref(false)


// author.id llega como string desde la API y el id del store es número: comparar como número
const isOwner = computed(() => {
  if (!userStore.getUser?.id) return false
  return Number(props.publicacion.author?.id) === Number(userStore.getUser.id)
})
const actionsAvailable = computed(() => !props.disableActions && !isOwner.value && !props.isPreview)

const isNegotiable = computed(() => {
  const val = props.publicacion?.acf?.precio_negociable

  if (val === undefined || val === null)
    return false

  if (typeof val === 'string') {
    const lowerVal = val.toLowerCase().trim()
    
    // Valores legacy: 'Sí' (con tilde) también es "Consultar precio"
    return lowerVal === 'si' || lowerVal === 'sí' || lowerVal === 'yes' || lowerVal === 'true'
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
  const text = t('product_details.share_text', { title: props.publicacion.title })

  return [
    {
      name: t('product_details.share_whatsapp'),
      icon: 'tabler-brand-whatsapp',
      color: '#25D366',
      action: () => {
        window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank')
      }
    },
    {
      name: t('product_details.share_telegram'),
      icon: 'tabler-brand-telegram',
      color: '#0088cc',
      action: () => {
        window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank')
      }
    },
    {
      name: t('product_details.share_email'),
      icon: 'tabler-mail',
      color: 'primary',
      action: () => {
        window.open(`mailto:?subject=${encodeURIComponent(props.publicacion.title)}&body=${encodeURIComponent(text + ' ' + url)}`, '_self')
      }
    },
    {
      name: t('product_details.share_copy_link'),
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

// Check if the current product has a price already requested in this session (for guests)
const hasRequestedPrice = computed(() => {
  try {
    const list = JSON.parse(localStorage.getItem('motorlan_requested_prices') || '[]')
    return list.includes(props.publicacion.id)
  } catch (e) {
    return false
  }
})

const chatButtonText = computed(() => {
  if (hasRequestedPrice.value) return t('product_details.open_chat')
  return t('product_details.consult_price_button')
})

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
  offerError.value = ''
  try {
    const { data: res } = assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/${props.publicacion.id}/offers`).post({
      amount: offerAmount.value,
      justification: offerMessage.value,
    }).json())
    // El backend responde { success, message, data: oferta }
    offer.value = res.value?.data ?? null
    isOfferDialogOpen.value = false
  }
  catch (error: any) {
    console.error(error)
    // Mostrar el motivo (límite diario, sin stock, publicación propia...) en el diálogo
    offerError.value = error?.message || t('product_details.offer_error', 'No se pudo enviar la oferta')
  }
  finally {
    isSubmittingOffer.value = false
  }
}

const removeOffer = async () => {
  if (!offer.value)
    return
  try {
    // Una sola ejecución: useApi(url).delete().execute() lanzaba la petición dos veces
    const req = useApi(`/wp-json/motorlan/v1/offers/${offer.value.id}`, { immediate: false }).delete().json()
    await req.execute()
    assertApiOk(req)
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
        <span class="text-body-2 font-weight-medium">{{ t('product_details.favorite') }}</span>
      </div>
      <div
        class="d-flex align-center gap-2 pointer"
        @click="share"
      >
        <VIcon
          icon="tabler-share"
          color="error"
        />
        <span class="text-body-2 font-weight-medium">{{ t('product_details.share') }}</span>
      </div>
    </div>
    <VDivider class="mb-6 opacity-20" />

    <VCard class="mb-6 detail-card product-detail-card-enhanced pa-4">
      <VCardText>
        <div class="mb-4">
          <h3 class="text-h6 mb-1">{{ productTitleUpper }}</h3>
        </div>
        <VRow class="motor-details" dense>
          <!-- Precio visible: no tiene "Consultar precio" marcado, o es el owner -->
          <VCol v-if="showPrice" cols="12">
            <div class="detail-item d-flex align-center mb-2">
              <span class="text-h5 font-weight-bold text-primary">{{ Number(props.publicacion.acf.precio_de_venta).toLocaleString(locale) }} €</span>
              <VChip v-if="isConsultPrice && isOwner" size="x-small" color="info" variant="tonal" class="ml-2">{{ t('product_details.reference') }}</VChip>
            </div>
          </VCol>
          <!-- Consultar precio marcado (publico) o sin precio -->
          <VCol v-else-if="isConsultPrice" cols="12">
            <div class="detail-item d-flex align-center mb-2">
              <VIcon icon="tabler-currency-euro" class="mr-1" />
              <span class="text-h6 font-weight-bold text-warning">{{ t('product_details.consult_price') }}</span>
            </div>
          </VCol>
          <VCol v-else cols="12">
            <div class="detail-item d-flex align-center mb-2">
              <VIcon icon="tabler-currency-euro" class="mr-1" />
              <span class="text-body-1 font-weight-medium">{{ t('product_details.consult_price') }}</span>
            </div>
          </VCol>
        <!--<VCol cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-cube" class="mr-1" />
              <span>{{ props.publicacion.tipo[0].name || 'N/A' }}</span>
            </div>
          </VCol>-->  
          <VCol v-if="tipoDisplay" cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-category" class="mr-1" />
              <span>{{ tipoDisplay }}</span>
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
              <span>{{ t('product_details.stock_label') }}: {{ props.publicacion.acf.stock ?? t('product_details.not_available') }}</span>
            </div>
          </VCol>
          <VCol v-if="sellerSales !== null" cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-chart-bar" class="mr-1" />
              <span>{{ t('product_details.sales_label') }}: {{ sellerSales }}</span>
            </div>
          </VCol>
        <VCol v-if="props.publicacion.acf.garantia_motorlan" cols="12" sm="6">
          <div class="detail-item d-flex align-center">
            <VIcon icon="tabler-shield-check" color="success" class="mr-1" />
            <span class="text-success">{{ t('product_details.warranty_motorlan') }}</span>
          </div>
        </VCol>
      </VRow>
      </VCardText>
    </VCard>

    <div
      v-if="props.publicacion.acf.descripcion"
      class="contact-card pa-4 mb-6"
    >
      <!-- div (no p): la descripción puede traer listas y párrafos del editor -->
      <div
        class="rich-text"
        v-html="sanitize(props.publicacion.acf.descripcion)"
      />
    </div>

    <div class="d-flex flex-wrap gap-4 mb-6">
      <template v-if="actionsAvailable">
        <VBtn
          color="error"
          class="px-6 flex-grow-1 action-btn"
          @click="openChatModal"
        >
          {{ t('product_details.consult_price_button') }}
        </VBtn>
        <!-- Botones de oferta eliminados - ya no aplica "Precio negociable" -->
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
            <span>{{ offer ? t('product_details.edit_offer') : t('product_details.make_offer') }}</span>
          </div>
          <VBtn icon="tabler-x" variant="text" @click="isOfferDialogOpen = false" />
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 mb-3">
            {{ t('product_details.offer_description') }}
          </p>
          <VTextField
            v-model.number="offerAmount"
            :label="t('product_details.offer_price')"
            type="number"
            class="mt-4"
            :rules="[
              (v: number | null) => !!v || t('product_details.amount_required'),
              (v: number | null) => (v && v > 0) || t('product_details.amount_greater_than_zero')
            ]"
          />
          <VTextarea
            v-model="offerMessage"
            :label="t('product_details.offer_message')"
            class="mt-4"
            rows="3"
          />
          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            {{ t('product_details.no_charge_until_accept') }}
          </VAlert>
          <VAlert
            v-if="offerError"
            type="error"
            variant="tonal"
            class="mt-4"
          >
            {{ offerError }}
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
            {{ t('product_details.send_offer') }}
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
          <span class="text-h6">{{ t('product_details.share_publication') }}</span>
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
      {{ t('product_details.link_copied') }}
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

@media (max-width: 959px) {
  .detail-card,
  .contact-card {
    padding: 0.875rem !important;
  }

  .detail-item,
  .contact-card :deep(.v-row) {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
  }

  .action-btn {
    width: 100%;
  }

  .offer-modal,
  .share-modal {
    border-radius: 20px;
  }

  .offer-modal :deep(.v-card-title),
  .share-modal :deep(.v-card-title) {
    align-items: flex-start;
    gap: 0.75rem;
  }

  .offer-modal :deep(.v-card-actions),
  .share-modal :deep(.v-card-actions) {
    padding-inline: 1rem;
    padding-bottom: 1rem;
  }

  .share-modal :deep(.v-btn) {
    min-height: 48px;
  }
}
</style>
