<script setup lang="ts">
import type { Motor } from '@/interfaces/motor'

defineProps<{ motors: Motor[]; loading: boolean }>()
</script>

<template>
  <div v-if="loading && !motors.length" class="text-center pa-12">
    <VProgressCircular indeterminate size="64" />
    <p class="mt-4">Cargando motores...</p>
  </div>

  <VRow v-else-if="motors.length" class="motor-grid">
    <VCol v-for="motor in motors" :key="motor.id" cols="12" sm="6" md="4">
      <div class="motor-card pa-4">
        <div class="motor-image mb-6">
          <img :src="motor.imagen_destacada?.url || '/placeholder.png'" alt="" />
        </div>
        <div class="text-error text-body-1 mb-4">{{ motor.title }}</div>
        <div class="d-flex justify-space-between align-center">
          <VBtn
            color="error"
            class="rounded-pill px-6"
            :to="`/tienda/${motor.slug}`"
          >
            + INFO
          </VBtn>
          <div class="price text-error font-weight-bold">
            {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
          </div>
        </div>
      </div>
    </VCol>
  </VRow>

  <VCard v-else class="pa-8 text-center">
    <VCardText>
      <p class="text-h6">No se encontraron motores</p>
      <p>Intenta ajustar los filtros de búsqueda.</p>
    </VCardText>
  </VCard>
</template>

<style scoped>
.motor-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.motor-image {
  height: 185px;
  border-radius: 8px;
  background: #EEF1F4;
  display: flex;
  align-items: center;
  justify-content: center;
}
.motor-image img {
  max-width: 100%;
  max-height: 100%;
}
.price {
  font-size: 24px;
}
</style>

