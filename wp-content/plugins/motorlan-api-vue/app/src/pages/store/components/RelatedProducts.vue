<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { createUrl } from '@/@core/composable/createUrl'
import { usePublicApi } from '@/composables/usePublicApi'
import type { Publicacion } from '@/interfaces/publicacion'
import { uiSlugForTipo } from '@/utils/tipoMapping'

const props = defineProps<{ currentId: number; tipo?: string }>()
const { t } = useI18n()

// Relacionados = mismo tipo (motor/regulador/repuesto) que la ficha actual, desde el
// endpoint cacheado de la tienda (listado ligero, sin galerías). Antes eran las 8 últimas
// publicaciones de /publicaciones, sin caché y con la galería completa de cada una.
const { data } = usePublicApi<any>(
  createUrl('/wp-json/motorlan/v1/store/publicaciones', {
    query: computed(() => ({
      per_page: 5,
      page: 1,
      status: 'publish',
      orderby: 'date',
      order: 'desc',
      ...(props.tipo ? { tipo: props.tipo } : {}),
    })),
  }),
  { refetch: true },
).get().json()

const products = computed(() => {
  const all = data.value?.data || []

  return all
    .filter((m: Publicacion) => Number(m.id) !== Number(props.currentId))
    .slice(0, 4)
})

const formatProductTitle = (publication: Publicacion) => publication?.title || ''

const resolveProductType = (publication: Publicacion) => {
  const tipos = Array.isArray(publication?.tipo) ? publication.tipo : []
  if (!tipos.length)
    return t('select_publication_type.motor_title')

  switch (uiSlugForTipo(tipos[0].slug || '', publication.acf?.tipo_de_alimentacion)) {
    case 'motor-ac': return t('select_publication_type.motor_ac_title')
    case 'motor-dc': return t('select_publication_type.motor_dc_title')
    case 'regulador': return t('select_publication_type.regulator_title')
    case 'otro-repuesto': return t('select_publication_type.other_spare_part_title')
    default: return tipos[0].name || t('select_publication_type.motor_title')
  }
}
</script>

<template>
  <section
    v-if="products.length"
    class="related-products"
  >
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
      <h3 class="mb-0 text-h5 font-weight-bold">
        {{ t('store.related.title') }}
      </h3>
      <span class="text-body-2 text-medium-emphasis">
        {{ t('store.related.subtitle') }}
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
                {{ t('store.related.more_info') }}
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
  align-self: flex-start;
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
