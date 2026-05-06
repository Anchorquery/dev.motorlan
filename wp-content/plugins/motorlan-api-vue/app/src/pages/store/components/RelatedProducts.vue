<script setup lang="ts">
import { computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{ currentId: number }>()

const { data } =  useApi<any>(
  createUrl('/wp-json/motorlan/v1/publicaciones', { query: { per_page: 8, status: 'publish' } })
).get().json()

const products = computed(() => {
  const all = data.value?.data || []
  return all
    .filter((m: Publicacion) => m.id !== props.currentId && m.status === 'publish')
    .slice(0, 4)
})

const formatProductTitle = (publication: Publicacion) => {
  const acf = publication?.acf || {}
  
  // Try to use the same logic as in [slug].vue if possible, but keeping it simple here
  const tipo = publication?.tipo && publication.tipo.length > 0 ? publication.tipo[0].name : ''
  const marca = (publication as any).marca_name || ''
  const modelo = acf.tipo_o_referencia || ''
  
  let powerOrTorque = ''
  if (acf.potencia) {
    powerOrTorque = `${acf.potencia} kW`
  } else if (acf.par_nominal) {
    powerOrTorque = `${acf.par_nominal} Nm`
  }

  const velocidad = acf.velocidad ? `${acf.velocidad} rpm` : ''

  const parts = [
    tipo,
    marca,
    modelo,
    powerOrTorque,
    velocidad,
  ].filter(p => !!p && String(p).trim() !== '')

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
          <div class="mt-auto pt-2">
            <VBtn
              color="error"
              variant="elevated"
              class="rounded-pill px-6"
              :to="'/' + publicacion.slug"
            >
              + INFO
            </VBtn>
          </div>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.related-products .motor-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.related-products .motor-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

.related-products .motor-image {
  height: 160px;
  border-radius: 12px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.related-products .motor-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 8px;
}

.related-products .motor-title {
  font-family: 'Inter', sans-serif;
  font-weight: 500;
  color: #334155;
  line-height: 1.4;
  height: 2.8em;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.9rem !important;
}

.related-products .motor-type {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  font-weight: 600;
  font-size: 0.7rem !important;
}

.related-products h3 {
  font-family: 'Inter', sans-serif;
  color: #1e293b;
  font-weight: 600;
}
</style>
