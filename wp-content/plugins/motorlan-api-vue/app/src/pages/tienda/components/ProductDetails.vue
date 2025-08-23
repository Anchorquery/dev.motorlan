<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()
const FAVORITES_KEY = 'motor-favorites'

const isFavorite = ref(false)
const sellerName = ref('')
const sellerRating = ref<number | null>(null)
const location = computed(() => {
  const { pais, provincia } = props.motor.acf
  if (pais && provincia)
    return `${pais} / ${provincia}`
  return pais || provincia || ''
})
const price = computed(() =>
  props.motor.acf.precio_de_venta
    ? `${props.motor.acf.precio_de_venta} €`
    : 'Consultar precio',
)

const negotiableLabel = computed(() => {
  const val = props.motor.acf.precio_negociable
  if (typeof val === 'string')
    return val.toLowerCase() === 'si' ? 'Negociable' : 'No negociable'
  return val ? 'Negociable' : 'No negociable'
})

const categories = computed(() => props.motor.categories.map(c => c.name).join(', '))
const brand = computed(() => props.motor.acf.marca?.name || props.motor.acf.marca)
onMounted(async () => {
  try {
    const saved = JSON.parse(localStorage.getItem(FAVORITES_KEY) || '[]') as number[]

    isFavorite.value = saved.includes(props.motor.id)
  }
  catch {
    // ignore
  }

  try {
    const user = await $api(`/wp-json/wp/v2/users/${props.motor.author_id}`)
    sellerName.value = user.name
    const rating = Number(user.acf?.calificacion)
    sellerRating.value = Number.isFinite(rating) ? rating : null
  }
  catch {
    // ignore
  }

  if (isLoggedIn.value && isNegotiable.value) {
    try {
      const res = await $api(`/wp-json/motorlan/v1/motors/${props.motor.id}/offers`)
      if (res) {
        offer.value = res
        offerAmount.value = Number(res.monto)
        offerMessage.value = res.justificacion || ''
      }
    }
    catch {
      // ignore
    }
  }
})

const toggleFavorite = () => {
  try {
    const saved = JSON.parse(localStorage.getItem(FAVORITES_KEY) || '[]') as number[]
    if (isFavorite.value)
      localStorage.setItem(FAVORITES_KEY, JSON.stringify(saved.filter(id => id !== props.motor.id)))
    else
      localStorage.setItem(FAVORITES_KEY, JSON.stringify([...saved, props.motor.id]))
  }
  catch {
    // ignore
  }

  isFavorite.value = !isFavorite.value
}

const shareSnackbar = ref(false)
const loginSnackbar = ref(false)
const isOfferDialogOpen = ref(false)
const offerAmount = ref<number | null>(null)
const offerMessage = ref('')
const offer = ref<any | null>(null)
const isLoggedIn = computed(() => !!useCookie('userData').value)
const isNegotiable = computed(() => {
  const val = props.motor.acf.precio_negociable
  return typeof val === 'string' ? val.toLowerCase() === 'si' : !!val
})

const share = () => {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({
      title: props.motor.title,
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
    const res = await $api('/wp-json/motorlan/v1/purchases', {
      method: 'POST',
      body: { motor_id: props.motor.id },
    })
    router.push(`/tienda/compra/${res.uuid}`)
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
  if (offerAmount.value === null || offerAmount.value >= Number(props.motor.acf.precio_de_venta || Infinity))
    return

  try {
    const res = await $api(`/wp-json/motorlan/v1/motors/${props.motor.id}/offers`, {
      method: 'POST',
      body: {
        monto: offerAmount.value,
        justificacion: offerMessage.value,
      },
    })
    offer.value = res
    isOfferDialogOpen.value = false
  }
  catch (error) {
    console.error(error)
  }
}

const removeOffer = async () => {
  if (!offer.value)
    return
  try {
    await $api(`/wp-json/motorlan/v1/offers/${offer.value.id}`, { method: 'DELETE' })
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
        class="d-flex align-center gap-2 pointer"
        @click="toggleFavorite"
      >
        <VIcon
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
          <h3 class="text-h6 mb-1">{{ props.motor.title }}</h3>
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
          <VCol v-if="props.motor.acf.garantia_motorlan" cols="12" sm="6">
            <div class="detail-item d-flex align-center">
              <VIcon icon="tabler-shield-check" color="success" class="mr-1" />
              <span class="text-success">Garantía Motorlan</span>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div
      v-if="props.motor.acf.descripcion"
      class="contact-card pa-4 mb-6"
    >
      <p v-html="props.motor.acf.descripcion" />
    </div>

    <div class="d-flex flex-wrap gap-4 mb-6">
      <VBtn
        color="error"
        class="px-6 flex-grow-1 action-btn"
        @click="isConfirmDialogOpen = true"
      >
        Comprar
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
            :rules="[v => !v || v < Number(props.motor.acf.precio_de_venta) || 'Debe ser menor al precio']"
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
