<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
import { createUrl } from '@/@core/composable/createUrl'
import { assertApiOk, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useUserStore } from '@/@core/stores/user'
import type { ImagenDestacada, Publicacion } from '@/interfaces/publicacion'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import { storeUrl } from '@/utils/urls'

const router = useRouter()
const userStore = useUserStore()
const { showToast } = useToast()
const { formatMotorName } = useMotorFormatter()

const currentUser = computed(() => userStore.getUser)

const headers = computed(() => [
  { title: t('publication_list.publication'), key: 'title' },
  { title: t('publication_list.reference'), key: 'referencia', sortable: false },
  { title: t('publication_list.price'), key: 'precio', sortable: false },
  { title: t('publication_list.status'), key: 'status', sortable: false },
  { title: t('publication_list.actions'), key: 'actions', sortable: false },
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

const {
  data: favoritesData,
  execute: fetchFavorites,
  isFetching,
} = useApi<any>(createUrl('/wp-json/motorlan/v1/purchases/favorites', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}), { immediate: false }).get().json()

const favorites = computed((): Publicacion[] => favoritesData.value?.data || [])
// /purchases/favorites pagina en el servidor y devuelve pagination.total
const totalFavorites = computed(() => favoritesData.value?.pagination?.total ?? favorites.value.length)

const refreshFavorites = async () => {
  try {
    await fetchFavorites()
  }
  catch (error) {
    console.error('Error fetching favorites:', error)
  }
}

watch([page, itemsPerPage, sortBy, orderBy, searchQuery], () => {
  void refreshFavorites()
}, { immediate: true })

watch(searchQuery, () => {
  if (page.value !== 1)
    page.value = 1
})

const resolveStatus = (status: string) => {
  if (status === 'publish')
    return { text: t('custom.published'), color: 'success' }
  if (status === 'draft')
    return { text: t('custom.draft'), color: 'secondary' }
  if (status === 'paused')
    return { text: t('custom.paused'), color: 'warning' }
  if (status === 'sold')
    return { text: t('publication_list.status_options.sold'), color: 'error' }

  return { text: t('custom.unknown'), color: 'info' }
}

const removeFavorite = async (motorId: number) => {
  try {
    const request = useApi(`/wp-json/motorlan/v1/favorites/${motorId}`, { immediate: false }).delete().json()
    await request.execute()
    assertApiOk(request)
    await refreshFavorites()
  }
  catch (error) {
    console.error('Error removing favorite:', error)
    showToast(t('product_details.favorite_error', 'No se pudo actualizar el favorito. Inténtalo de nuevo.'), 'error')
  }
}

const goToDetail = (item: Publicacion) => {
  // Navegar a la tienda con URL absoluta (no usar router.push porque estamos en otra base)
  window.open(storeUrl(item.slug), '_blank')
}

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
    id="favorite-list"
    class="motor-card-enhanced"
  >
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">{{ t('dashboard_lists.my_favorites') }}</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <div class="dash-simple-toolbar d-flex flex-wrap gap-4">
        <div class="d-flex align-center dash-simple-toolbar__search">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('dashboard_lists.search_favorite')"
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
      :items="favorites"
      :items-length="totalFavorites"
      :loading="isFetching"
      class="text-no-wrap pb-4"
      @update:options="updateOptions"
    >
      <!-- publicacion -->
      <template #item.title="{ item }">
        <div class="d-flex align-center gap-x-4 py-3">
          <VAvatar
            v-if="item.imagen_destacada"
            size="44"
            variant="tonal"
            rounded
            class="border"
            :image="getImageBySize(item.imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-premium-title"
              :class="{ 'cursor-pointer': (item as any).author?.id === currentUser?.id || currentUser?.isAdmin }"
              @click="(item as any).author?.id === currentUser?.id || currentUser?.isAdmin ? router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`) : null"
            >{{ formatMotorName(item) || item.title }}</span>
            <span class="text-body-2 text-muted">{{ (item.acf?.marca as any)?.name || item.marca_name }}</span>
          </div>
        </div>
      </template>

      <!-- referencia -->
      <template #item.referencia="{ item }">
        <span class="text-body-2 text-high-emphasis">{{ item.acf?.tipo_o_referencia || '—' }}</span>
      </template>

      <!-- precio -->
      <template #item.precio="{ item }">
        <span v-if="['yes', 'Sí', 'si'].includes(String(item.acf?.precio_negociable)) || item.acf?.precio_negociable === true" class="text-body-1 font-weight-medium text-warning">
          {{ t('product_details.consult_price') }}
        </span>
        <span v-else-if="item.acf?.precio_de_venta" class="text-body-1 font-weight-medium text-premium-price">
          {{ formatCurrency(item.acf.precio_de_venta) }}
        </span>
        <span v-else class="text-body-1 font-weight-medium text-premium-price">
          {{ t('product_details.consult_price') }}
        </span>
      </template>

      <!-- status -->
      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus(item.status)"
          density="comfortable"
          label
          size="small"
          class="font-weight-medium"
        >
          {{ resolveStatus(item.status).text }}
        </VChip>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn
            color="primary"
            variant="tonal"
            size="small"
            @click="goToDetail(item)"
          >
            <VIcon icon="tabler-eye" size="18" />
          </IconBtn>
          <IconBtn
            color="error"
            variant="tonal"
            size="small"
            @click="removeFavorite(item.id)"
          >
            <VIcon icon="tabler-trash" size="18" />
          </IconBtn>
        </div>
      </template>

      <!-- pagination -->
      <template #bottom>
        <VDivider />
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalFavorites"
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
