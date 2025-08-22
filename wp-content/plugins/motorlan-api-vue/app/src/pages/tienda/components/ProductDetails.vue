<script setup lang="ts">
import { ref } from 'vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()



const form = ref({
  message: '',
  name: '',
  email: '',
  phone: '',
})
</script>

<template>
  <div class="product-details flex-grow-1">

    <div class="d-flex flex-wrap gap-4 mb-6">
      <VBtn
        color="error"
        class="rounded-pill px-6 flex-grow-1"
      >
        Comprar
      </VBtn>
      <VBtn
        variant="outlined"
        color="error"
        class="rounded-pill px-6 flex-grow-1"
      >
        Hacer una oferta
      </VBtn>
      <div class="d-flex align-center gap-2 ms-auto">
        <span class="text-body-2 font-weight-medium">Compartir</span>
        <VBtn
          icon="mdi-facebook"
          variant="text"
          color="error"
        />
        <VBtn
          icon="mdi-share-variant"
          variant="text"
          color="error"
        />
      </div>
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
.product-details h1 {
  font-size: 24px;
}
.contact-card {
  border: 1px solid #E6E6E6;
  border-radius: 8px;
}
</style>
