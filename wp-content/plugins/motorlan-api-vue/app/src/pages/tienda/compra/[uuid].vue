<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const uuid = route.params.uuid as string

const { data } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`)).get().json()
const purchase = computed(() => data.value?.data)

// Fetch vendor details
const vendorId = data.value?.data?.vendedor
const { data: vendorData } = await useApi<any>(createUrl(`/wp-json/wp/v2/users/${vendorId}`)).get().json()
const vendor = computed(() => vendorData.value)

const opinion = ref({ valoracion: 0, comentario: '' })

const sendOpinion = async () => {
  // Se construye la URL dinámicamente
  const url = `/wp-json/motorlan/v1/purchases/${uuid.value}/opinion`

  // Se ejecuta la petición POST con useApi
  const { error } = await useApi(url).post(opinion.value)

  // Se maneja el error si la petición falla
  if (error.value) {
    console.error('Error al enviar la opinión:', error.value)

    return // Se detiene la ejecución si hay un error
  }

  // Si la petición fue exitosa, se resetea el formulario
  opinion.value = { valoracion: 0, comentario: '' }
}
</script>

<template>
  <VContainer
    v-if="purchase"
    fluid
  >
    <VCard class="mb-6">
      <VCardTitle>Detalle de la compra</VCardTitle>
      <VCardText>
        <div class="d-flex">
          <VImg
            :src="purchase.motor?.acf?.motor_image?.url"
            width="120"
            class="me-4 rounded"
            cover
          />
          <div class="flex-grow-1">
            <div class="text-h6">{{ purchase.motor?.title }}</div>
            <div>{{ purchase.fecha_compra }}</div>
            <div>Estado: {{ purchase.estado }}</div>
            <div v-if="vendor">Vendedor: {{ vendor.name }}</div>
            <RouterLink
              :to="{ name: 'apps-chat', query: { user: purchase.vendedor } }"
              class="d-block mt-2"
            >
              Chatear con el vendedor
            </RouterLink>
          </div>
          <div class="text-h6 ms-auto">
            {{ purchase.motor?.acf?.precio_de_venta ? `${purchase.motor.acf.precio_de_venta} €` : '' }}
          </div>
        </div>
      </VCardText>
    </VCard>

    <VCard>
      <VCardTitle>¿Qué te pareció tu producto?</VCardTitle>
      <VCardText>
        <VRating
          v-model="opinion.valoracion"
          class="mb-4"
        />
        <VTextarea
          v-model="opinion.comentario"
          label="Comentario"
          rows="3"
        />
        <VBtn
          color="error"
          class="mt-4"
          @click="sendOpinion"
        >
          Enviar
        </VBtn>
      </VCardText>
    </VCard>
  </VContainer>
</template>
