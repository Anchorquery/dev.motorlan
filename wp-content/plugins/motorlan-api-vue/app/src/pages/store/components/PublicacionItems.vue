<script setup lang="ts">
import type { Publicacion } from '@/interfaces/publicacion'

defineProps<{ publicaciones: Publicacion[]; loading: boolean }>()
</script>

<template>
  <div v-if="loading" class="text-center py-12">
    <VProgressCircular
      indeterminate
      size="64"
      color="error"
    />
    <p class="mt-4 text-medium-emphasis">
      Cargando publicaciones...
    </p>
  </div>

  <template v-else>
    <VRow
      v-if="publicaciones.length"
      class="motor-grid"
    >
      <VCol
        v-for="publicacion in publicaciones"
        :key="publicacion.id"
        cols="12"
        sm="6"
        lg="4"
        xl="3"
        class="d-flex"
      >
        <VCard class="motor-card-enhanced flex-grow-1">
          <VCardText class="pa-4 d-flex flex-column h-100">
            <div class="motor-image mb-4">
              <VImg
                :src="(!Array.isArray(publicacion.imagen_destacada) && publicacion.imagen_destacada?.url) || '/placeholder.png'"
                :alt="publicacion.title"
                cover
                aspect-ratio="1.25"
                class="motor-image__media"
              />
            </div>

            <div class="text-error text-premium-title text-subtitle-1 font-weight-bold mb-2 motor-card__title">
              {{ publicacion.title }}
            </div>

            <div class="text-body-2 text-medium-emphasis mb-4 motor-card__meta">
              Explora los detalles de esta publicacion y revisa disponibilidad, caracteristicas y contacto.
            </div>

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
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-else
      class="text-center py-12"
    >
      <p class="text-h6 mb-2">
        No se encontraron publicaciones
      </p>
      <p class="text-medium-emphasis mb-0">
        Intenta ajustar los filtros de busqueda.
      </p>
    </div>
  </template>
</template>

<style scoped>
.motor-card-enhanced {
  border: 1px solid rgba(218, 41, 28, 0.08);
  border-radius: 22px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 251, 250, 0.98));
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
  transition:
    transform 180ms ease,
    box-shadow 180ms ease,
    border-color 180ms ease;
}

.motor-card-enhanced:hover {
  transform: translateY(-4px);
  box-shadow: 0 20px 36px rgba(15, 23, 42, 0.1);
  border-color: rgba(218, 41, 28, 0.18);
}

.motor-image {
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
}

.motor-image__media {
  border-radius: 18px;
}

.motor-card__title {
  line-height: 1.25;
  min-height: 2.5em;
}

.motor-card__meta {
  line-height: 1.5;
}
</style>
