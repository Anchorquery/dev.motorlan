<script setup lang="ts">
import { computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{ currentId: number }>()

const { data } = useApi<any>(
  createUrl('/wp-json/motorlan/v1/publicaciones', { query: { per_page: 8, status: 'publish' } }),
).get().json()

const products = computed(() => {
  const all = data.value?.data || []

  return all
    .filter((m: Publicacion) => m.id !== props.currentId && m.status === 'publish')
    .slice(0, 4)
})

const formatProductTitle = (publication: Publicacion) => publication?.title || ''

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
  <section
    v-if="products.length"
    class="related-products"
  >
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
      <h3 class="mb-0 text-h5 font-weight-bold">
        Productos relacionados
      </h3>
      <span class="text-body-2 text-medium-emphasis">
        Otras publicaciones que pueden interesarte
      </span>
    </div>

    <VRow>
      <VCol
        v-for="publicacion in products"
        :key="publicacion.id"
        cols="12"
        sm="6"
        md="3"
        class="d-flex"
      >
        <VCard class="related-card flex-grow-1" rounded="xl">
          <div class="related-card__image">
            <VImg
              :src="publicacion.imagen_destacada?.url || '/placeholder.png'"
              :alt="publicacion.title"
              cover
              aspect-ratio="1.25"
            />
          </div>

          <VCardText class="d-flex flex-column flex-grow-1 pa-4">
            <div class="text-body-1 mb-2 related-card__title">
              {{ formatProductTitle(publicacion) }}
            </div>

            <div class="text-caption text-medium-emphasis mb-3 related-card__type">
              {{ resolveProductType(publicacion) }}
            </div>

            <div class="mt-auto pt-2">
              <VBtn
                color="error"
                variant="tonal"
                class="rounded-pill px-6"
                :to="'/' + publicacion.slug"
                block
              >
                + Info
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<style scoped>
.related-products {
  margin-top: 1rem;
}

.related-card {
  border: 1px solid rgba(218, 41, 28, 0.08);
  box-shadow: 0 12px 24px rgba(20, 20, 43, 0.06);
  transition:
    transform 180ms ease,
    box-shadow 180ms ease;
  overflow: hidden;
  background: #fff;
}

.related-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 18px 34px rgba(20, 20, 43, 0.1);
}

.related-card__image {
  background: #f8fafc;
}

.related-card__title {
  font-weight: 600;
  color: #334155;
  line-height: 1.35;
  min-height: 2.7em;
}

.related-card__type {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  font-weight: 600;
}

@media (max-width: 959px) {
  .related-products {
    margin-top: 0.5rem;
  }
}
</style>
