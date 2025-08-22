<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()
const FAVORITES_KEY = 'motor-favorites'

const isFavorite = ref(false)

onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(FAVORITES_KEY) || '[]') as number[]

    isFavorite.value = saved.includes(props.motor.id)
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
  // 1. Se mantiene la validación inicial
  if (!confirmed)
    return

  // 2. Se utiliza useApi para la petición POST
  const { data: res, error } = await useApi< { uuid: string } >(
    '/wp-json/motorlan/v1/purchases',
  ).post({ motor_id: props.motor.id }).json()

  // 3. Se maneja el error devuelto por el composable
  if (error.value) {
    console.error('Error al realizar la compra:', error.value)

    return
  }

  // 4. Si la petición es exitosa y tenemos datos, redirigimos
  if (res.value)
    router.push(`/tienda/compra/${res.value.uuid}`)
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
</style>
