<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { ImagenDestacada, Publicacion } from '@/interfaces/publicacion'

const router = useRouter()

const headers = [
  { title: 'Publicacion', key: 'title' },
  { title: 'Referencia', key: 'referencia' },
  { title: 'Precio', key: 'precio' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

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
} = useApi<any>(createUrl('/wp-json/motorlan/v1/favorites', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}), { immediate: false }).get().json()

const favorites = computed((): Publicacion[] => favoritesData.value?.data || [])
const totalFavorites = computed(() => favoritesData.value?.total ?? favorites.value.length)

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

const resolveStatus = (status: string) => {
  if (status === 'publish')
    return { text: 'Publicado', color: 'success' }
  if (status === 'draft')
    return { text: 'Borrador', color: 'secondary' }
  if (status === 'paused')
    return { text: 'Pausado', color: 'warning' }
  if (status === 'sold')
    return { text: 'Vendido', color: 'error' }

  return { text: 'Desconocido', color: 'info' }
}

const removeFavorite = async (motorId: number) => {
  try {
    const request = useApi(`/wp-json/motorlan/v1/favorites/${motorId}`, { immediate: false }).delete()
    await request.execute()
    if (request.error.value)
      throw request.error.value
    await refreshFavorites()
  }
  catch (error) {
    console.error('Error removing favorite:', error)
  }
}

const goToDetail = (item: Publicacion) => {
  router.push(`/${item.slug}`)
}

const getImageBySize = (image: ImagenDestacada | null | any[], size = 'thumbnail'): string => {
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
      <span class="text-h5 text-premium-title">Mis Favoritos</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <div class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar Favorito"
            style="inline-size: 260px;"
            class="me-3"
            prepend-inner-icon="tabler-search"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
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
              class="text-body-1 font-weight-medium text-premium-title cursor-pointer"
              @click="router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`)"
            >{{ item.title }}</span>
            <span class="text-body-2 text-muted">{{ (item.acf.marca as any)?.name }}</span>
          </div>
        </div>
      </template>

      <!-- referencia -->
      <template #item.referencia="{ item }">
        <span class="text-body-2 text-high-emphasis">{{ item.acf.tipo_o_referencia || '—' }}</span>
      </template>

      <!-- precio -->
      <template #item.precio="{ item }">
        <span class="text-body-1 font-weight-medium text-premium-price">
          {{ item.acf.precio_de_venta ? formatCurrency(item.acf.precio_de_venta) : 'Consultar' }}
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
  </VCard>
</template>
