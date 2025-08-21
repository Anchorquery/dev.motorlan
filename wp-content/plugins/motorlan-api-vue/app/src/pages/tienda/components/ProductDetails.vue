<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()

const title = computed(() => {
  const parts = [
    props.motor.title,
    props.motor.acf.tipo_o_referencia,
    props.motor.acf.potencia ? `${props.motor.acf.potencia} kW` : null,
    props.motor.acf.velocidad ? `${props.motor.acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ')
})

const form = ref({
  message: '',
  name: '',
  email: '',
  phone: '',
})
</script>

<template>
  <div class="product-details flex-grow-1">
    <div class="d-flex justify-space-between align-start mb-4">
      <h1 class="text-error">
        {{ title }}
      </h1>
      <div class="text-h5 text-error">
        {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
      </div>
    </div>
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
