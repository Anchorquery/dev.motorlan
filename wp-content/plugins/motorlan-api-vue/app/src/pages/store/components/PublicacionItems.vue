<script setup lang="ts">
import type { Publicacion } from '@/interfaces/publicacion'

defineProps<{ publicaciones: Publicacion[]; loading: boolean }>()

const generateTitle = (publicacion: Publicacion) => {
  if (!publicacion || !publicacion.acf)
    return ''

  // Nomenclature: Tipo de producto_Marca_Tipo/modelo_Potencia o Par_Velocidad
  
  // 1. Tipo
  const tipo = publicacion.tipo && publicacion.tipo.length > 0 ? publicacion.tipo[0].name : '';
  
  // 2. Marca
  const marca = (publicacion as any).marca_name || '';

  // 3. Modelo
  const modelo = publicacion.acf.tipo_o_referencia || '';

  // 4. Potencia o Par
  let powerOrTorque = '';
  if (publicacion.acf.potencia) {
      powerOrTorque = `${publicacion.acf.potencia} kW`;
  } else if (publicacion.acf.par_nominal) {
      powerOrTorque = `${publicacion.acf.par_nominal} Nm`;
  }

  // 5. Velocidad
  const velocidad = publicacion.acf.velocidad ? `${publicacion.acf.velocidad} rpm` : '';

  const parts = [
    tipo,
    marca,
    modelo,
    powerOrTorque,
    velocidad,
  ].filter(p => !!p && String(p).trim() !== '')

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
        <div class="motor-card-enhanced pa-4 h-100 d-flex flex-column">
          <div class="motor-image mb-4">
            <img :src="(!Array.isArray(publicacion.imagen_destacada) && publicacion.imagen_destacada?.url) || '/placeholder.png'" alt="" />
          </div>
          <div class="text-error text-premium-title text-body-1 mb-2">{{ generateTitle(publicacion) }}</div>
          
          <div class="mt-auto pt-2 d-flex justify-end align-center">
            <VBtn
              color="error"
              variant="tonal"
              class="rounded-pill px-6 font-weight-medium"
              :to="`/${publicacion.slug}`"
            >
              Ver detalle
            </VBtn>
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
/* Removed old .motor-card styles as they are replaced by global utilities */
.motor-image {
  height: 200px;
  border-radius: 12px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.motor-image img {
  max-width: 100%;
  max-height: 100%;
}
</style>
