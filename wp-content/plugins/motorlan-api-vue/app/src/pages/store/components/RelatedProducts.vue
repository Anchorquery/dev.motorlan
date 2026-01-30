<script setup lang="ts">
import { computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{ currentId: number }>()

const { data } =  useApi<any>(
  createUrl('/wp-json/motorlan/v1/publicaciones', { query: { per_page: 4 } })
).get().json()

const products = computed(() => (data.value?.data || []).filter((m: Publicacion) => m.id !== props.currentId))

const formatProductTitle = (publication: Publicacion) => {
  const acf = publication?.acf || {}
  const parts = [
    publication?.title,
    acf?.tipo_o_referencia,
    acf?.potencia ? `${acf.potencia} kW` : null,
    acf?.velocidad ? `${acf.velocidad} rpm` : null,
  ].filter(Boolean)

  return parts.join(' ').toUpperCase()
}

const resolveProductType = (publication: Publicacion) => {
  const candidates: string[] = []

  if (Array.isArray(publication?.tipo)) {
    publication.tipo.forEach(typeItem => {
      if (typeItem?.name)
        candidates.push(String(typeItem.name))
    })
  }

  const acfType = (publication?.acf as Record<string, any>)?.tipo || (publication?.acf as Record<string, any>)?.tipo_producto
  if (acfType)
    candidates.push(String(acfType))

  const normalized = candidates
    .map(name => name.trim())
    .filter(Boolean)

  const match = (keyword: string) => normalized.find(name => name.toLowerCase().includes(keyword))

  if (match('regulador'))
    return 'Regulador'
  if (match('repuesto'))
    return 'Repuesto'
  if (match('motor'))
    return 'Motor'

  return normalized[0] || 'Motor'
}
</script>

<template>
  <div
    v-if="products.length"
    class="related-products"
  >
    <h3 class="mb-4">
      Productos relacionados
    </h3>
    <VRow>
      <VCol
        v-for="publicacion in products"
        :key="publicacion.id"
        cols="12"
        sm="6"
        md="3"
      >
        <div class="motor-card pa-4">
          <div class="motor-image mb-4">
            <img
              :src="publicacion.imagen_destacada?.url || '/placeholder.png'"
              alt=""
            />
          </div>
          <div class="text-body-1 mb-2 motor-title">
            {{ formatProductTitle(publicacion) }}
          </div>
          <div class="text-caption text-medium-emphasis mb-3 motor-type">
            {{ resolveProductType(publicacion) }}
          </div>
          <VBtn
            color="error"
            class="rounded-pill px-6"
            :to="'/' + publicacion.slug"
          >
            + INFO
          </VBtn>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.related-products .motor-card {
  background: #fff;
  border-radius: 16px;
}
.related-products .motor-image {
  height: 135px;
  border-radius: 8px;
  background: #EEF1F4;
  display: flex;
  align-items: center;
  justify-content: center;
}
.related-products .motor-image img {
  max-width: 100%;
  max-height: 100%;
}
.related-products .motor-title {
  font-weight: 600;
  color: #1f2233;
}
.related-products .motor-type {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  background: #f1f3f8;
  color: #5b647d;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  font-weight: 600;
}
</style>
