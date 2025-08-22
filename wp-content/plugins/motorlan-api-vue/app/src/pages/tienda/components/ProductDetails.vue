<script setup lang="ts">
import { ref } from 'vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()

const isFavorite = ref(false)

const toggleFavorite = () => {
  isFavorite.value = !isFavorite.value
}

const share = () => {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({
      title: props.motor.title,
      url,
    })
  } else {
    navigator.clipboard.writeText(url)
    alert('Enlace copiado al portapapeles')
  }
}

const form = ref({
  message: '',
  name: '',
  email: '',
  phone: '',
})
</script>

<template>
  <div class="product-details flex-grow-1">

    <div class="d-flex align-center gap-6 mb-4">
      <div
        class="d-flex align-center gap-2 pointer"
        @click="toggleFavorite"
      >
        <VIcon
          :icon="isFavorite ? 'mdi-heart' : 'mdi-heart-outline'"
          color="error"
        />
        <span class="text-body-2 font-weight-medium">Favorito</span>
      </div>
      <div
        class="d-flex align-center gap-2 pointer"
        @click="share"
      >
        <VIcon
          icon="mdi-share-variant"
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
        Contactar ahora
      </h3>
      <VForm class="d-flex flex-column gap-4">
        <VTextarea
          v-model="form.message"
          label="Mensaje"
          rows="3"
        />
        <VTextField
          v-model="form.name"
          label="Nombre"
        />
        <VTextField
          v-model="form.email"
          label="Email"
        />
        <VTextField
          v-model="form.phone"
          label="Teléfono"
        />
        <VBtn
          color="error"
          class="rounded-pill align-self-start"
        >
          Enviar
        </VBtn>
      </VForm>
    </div>
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
