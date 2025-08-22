<script setup lang="ts">
import { ref, computed } from 'vue'
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
  try {
    await $api(`/wp-json/motorlan/v1/purchases/${uuid}/opinion`, {
      method: 'POST',
      body: opinion.value,
    })
    opinion.value = { valoracion: 0, comentario: '' }
  }
  catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <VContainer v-if="purchase" fluid>
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
        <VRating v-model="opinion.valoracion" class="mb-4" />
        <VTextarea v-model="opinion.comentario" label="Comentario" rows="3" />
        <VBtn color="error" class="mt-4" @click="sendOpinion">Enviar</VBtn>
      </VCardText>
    </VCard>
  </VContainer>
</template>
