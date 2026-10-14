<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ImagenDestacada } from '@/interfaces/publicacion'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import { storeUrl } from '@/utils/urls'

// Valoraciones hechas por el usuario (POST /reviews). El backend ya no lee solo el CPT
// 'opinion' antiguo: antes esta lista salía siempre vacía. Además usaba item.raw (no
// existe en Vuetify 3.8), NuxtLink y enlazaba al editor de una publicación ajena.
const { t } = useI18n()
const { formatMotorName } = useMotorFormatter()
const headers = computed(() => [
  { title: t('purchases_opinions.publication'), key: 'publicacion', sortable: false },
  { title: t('purchases_opinions.rating'), key: 'valoracion' },
  { title: t('purchases_opinions.comment'), key: 'comentario', sortable: false },
])

const searchQuery = ref('')

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// Update data table options
const updateOptions = (options: any) => {
  page.value = options.page
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const { data: opinionsData, execute: fetchOpinions, isFetching } = useApi<any>(createUrl('/wp-json/motorlan/v1/purchases/opinions', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}), { immediate: false }).get().json()

watch([page, itemsPerPage, sortBy, orderBy, searchQuery], () => {
  void fetchOpinions()
}, { immediate: true })

watch(searchQuery, () => {
  if (page.value !== 1)
    page.value = 1
})

const opinions = computed(() => opinionsData.value?.data || [])
const totalOpinions = computed(() => opinionsData.value?.pagination?.total || 0)

const getImageBySize = (image: any, size = 'thumbnail'): string => {
  if (!image)
    return ''
  if (typeof image === 'string')
    return image
  let imageObj: ImagenDestacada | null = null
  if (Array.isArray(image) && image.length > 0)
    imageObj = image[0]
  else if (image && !Array.isArray(image))
    imageObj = image as ImagenDestacada
  if (!imageObj)
    return ''
  if (imageObj.sizes && imageObj.sizes[size])
    return imageObj.sizes[size] as string
  return imageObj.url || ''
}
</script>

<template>
  <VCard
    id="opinion-list"
    class="motor-card-enhanced"
  >
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">{{ t('dashboard_lists.my_opinions') }}</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <div class="dash-simple-toolbar d-flex flex-wrap gap-4">
        <div class="d-flex align-center dash-simple-toolbar__search">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('dashboard_lists.search_opinion')"
            style="inline-size: 260px;"
            class="me-3"
            prepend-inner-icon="tabler-search"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center dash-simple-toolbar__per-page">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
            style="inline-size: 80px;"
          />
        </div>
      </div>
    </VCardText>

    <VDivider />

    <!-- 👉 Datatable  -->
    <div class="table-responsive-shell">
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="opinions"
        :items-length="totalOpinions"
        :loading="isFetching"
        class="text-no-wrap pb-4"
        @update:options="updateOptions"
      >
        <!-- publicacion -->
        <template #item.publicacion="{ item }: { item: any }">
          <div class="d-flex align-center gap-x-4 py-3">
            <VAvatar
              v-if="item.publicacion?.imagen_destacada"
              size="44"
              variant="tonal"
              rounded
              class="border"
              :image="getImageBySize(item.publicacion.imagen_destacada, 'thumbnail')"
            />
            <div class="d-flex flex-column">
              <a
                v-if="item.publicacion?.slug"
                :href="storeUrl(item.publicacion.slug)"
                target="_blank"
                class="text-body-1 font-weight-medium text-premium-title"
              >{{ formatMotorName(item.publicacion) || item.publicacion.title }}</a>
              <span
                v-else
                class="text-body-1 font-weight-medium text-premium-title"
              >{{ item.title }}</span>
              <span class="text-body-2 text-muted">
                {{ item.target_role === 'buyer' ? t('purchases_opinions.to_buyer', 'Valoración al comprador') : t('purchases_opinions.to_seller', 'Valoración al vendedor') }}
              </span>
            </div>
          </div>
        </template>

        <!-- valoracion -->
        <template #item.valoracion="{ item }: { item: any }">
          <VRating
            :model-value="Number(item.valoracion) || 0"
            readonly
            color="warning"
            density="compact"
            size="small"
          />
        </template>

        <!-- comentario -->
        <template #item.comentario="{ item }: { item: any }">
          <span class="text-body-2 text-high-emphasis text-wrap">{{ item.comentario }}</span>
        </template>

        <!-- pagination -->
        <template #bottom>
          <VDivider />
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalOpinions"
            class="pa-4"
          />
        </template>
      </VDataTableServer>
    </div>
  </VCard>
</template>

<style scoped>
.table-responsive-shell {
  overflow-x: auto;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}

@media (max-width: 959px) {
  .dash-simple-toolbar {
    flex-direction: column;
    gap: 0.75rem !important;
  }

  .dash-simple-toolbar :deep(.v-spacer) {
    display: none !important;
  }

  .dash-simple-toolbar__search,
  .dash-simple-toolbar__per-page {
    width: 100%;
  }

  .dash-simple-toolbar__search :deep(.v-field),
  .dash-simple-toolbar__search :deep(.v-input),
  .dash-simple-toolbar__search :deep(.v-text-field) {
    inline-size: 100% !important;
    max-width: 100% !important;
  }

  .dash-simple-toolbar__per-page :deep(.v-field),
  .dash-simple-toolbar__per-page :deep(.v-input),
  .dash-simple-toolbar__per-page :deep(.v-select) {
    inline-size: 100% !important;
    max-width: 100% !important;
  }
}
</style>
