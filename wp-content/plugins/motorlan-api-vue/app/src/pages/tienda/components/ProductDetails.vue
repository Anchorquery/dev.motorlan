<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()
const FAVORITES_KEY = 'motor-favorites'

const isFavorite = ref(false)
const sellerName = ref('')
const sellerRating = ref('N/A')
const location = computed(() => {
  const { pais, provincia } = props.motor.acf
  if (pais && provincia)
    return `${pais} / ${provincia}`
  return pais || provincia || ''
})

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
    sellerRating.value = user.acf?.calificacion ?? 'N/A'
  }
  catch {
    // ignore
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
    <div class="d-flex flex-wrap gap-4 mb-6">
      <VBtn
        color="error"
        class="px-6 flex-grow-1 action-btn"
        @click="isConfirmDialogOpen = true"
      >
        Comprar
      </VBtn>
      <VBtn
        variant="outlined"
        color="error"
        class="px-6 flex-grow-1 action-btn"
      >
        Hacer una oferta
      </VBtn>
    </div>

    <div class="detail-card pa-4 mb-6">
      <h3 class="mb-4">
        Detalles del motor
      </h3>
      <ul class="motor-details">
        <li><strong>Nombre:</strong> {{ props.motor.title }}</li>
        <li><strong>Precio:</strong> {{ props.motor.acf.precio_de_venta ? `${props.motor.acf.precio_de_venta} €` : 'Consultar precio' }}</li>
        <li><strong>Precio negociable:</strong> {{ props.motor.acf.precio_negociable || 'No' }}</li>
        <li><strong>Categoría:</strong> {{ props.motor.categories.map(c => c.name).join(', ') }}</li>
        <li><strong>Marca:</strong> {{ props.motor.acf.marca?.name || props.motor.acf.marca }}</li>
        <li><strong>País / Provincia:</strong> {{ location }}</li>
        <li><strong>Vendedor:</strong> {{ sellerName || 'N/A' }}</li>
        <li><strong>Calificación:</strong> {{ sellerRating }}</li>
        <li><strong>Garantía Motorlan:</strong> {{ props.motor.acf.garantia_motorlan ? 'Sí' : 'No' }}</li>
      </ul>
    </div>

    <div class="contact-card pa-4">
      <h3 class="mb-4">
        Descripción
      </h3>
      <p v-html="props.motor.acf.descripcion" />
    </div>
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
  list-style: none;
  padding: 0;
  margin: 0;
}

.motor-details li {
  margin-bottom: 0.5rem;
}
</style>
