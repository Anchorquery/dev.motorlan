<script setup lang="ts">
import type { Publicacion } from '@/interfaces/publicacion'

defineProps<{ publicaciones: Publicacion[]; loading: boolean }>()

const generateTitle = (publicacion: Publicacion) => {
  if (!publicacion || !publicacion.acf)
    return ''

  const parts = [
    publicacion.title,
    publicacion.acf.tipo_o_referencia,
    publicacion.acf.potencia ? `${publicacion.acf.potencia} kW` : null,
    publicacion.acf.velocidad ? `${publicacion.acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ').toUpperCase()
}
</script>

<template>
  <div v-if="loading" class="text-center pa-12">
    <VProgressCircular indeterminate size="64" />
    <p class="mt-4">Cargando publicaciones...</p>
  </div>

  <template v-else>
    <VRow v-if="publicaciones.length" class="motor-grid">
      <VCol v-for="publicacion in publicaciones" :key="publicacion.id" cols="12" sm="6" md="4">
        <div class="motor-card pa-4">
          <div class="motor-image mb-6">
            <img :src="publicacion.imagen_destacada?.url || '/placeholder.png'" alt="" />
          </div>
          <div class="text-error text-body-1 mb-4">{{ generateTitle(publicacion) }}</div>
          <div class="d-flex justify-space-between align-center">
            <VBtn
              color="error"
              class="rounded-pill px-6"
              :to="`/public-store/${publicacion.slug}`"
            >
              + INFO
            </VBtn>
            <div class="price text-error font-weight-bold">
              {{ publicacion.acf.precio_de_venta ? `${publicacion.acf.precio_de_venta} €` : 'Consultar precio' }}
            </div>
          </div>
        </div>
      </VCol>
    </VRow>

    <div v-else class="text-center pa-12">
      <p class="text-h6">No se encontraron publicaciones</p>
      <p>Intenta ajustar los filtros de búsqueda.</p>
    </div>
  </template>
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
