<script setup lang="ts">
import { computed, ref } from 'vue'
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
        Hacer una pregunta
      </VBtn>
      <VBtn
        variant="outlined"
        color="error"
        class="rounded-pill px-6 flex-grow-1"
      >
        Hacer una oferta
      </VBtn>
      <div class="d-flex align-center gap-2">
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
      <h3 class="text-error mb-4">
        Hacer una pregunta
      </h3>
      <VForm class="d-flex flex-column gap-4">
        <VTextarea
          v-model="form.message"
          label="Pregunta"
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
          Preguntar
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
