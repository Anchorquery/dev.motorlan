<script setup lang="ts">
import type { ImagenDestacada } from '../../../../../interfaces/motor'

const headers = [
  { title: 'Motor', key: 'motor' },
  { title: 'Referencia', key: 'referencia' },
  { title: 'Precio', key: 'precio' },
]

const searchQuery = ref('')
const selectedRows = ref([])

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

const { data: favoritesData, execute: fetchFavorites } = await useApi<any>(createUrl('/wp-json/motorlan/v1/my-account/favorites', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}))

const favorites = computed(() => favoritesData.value?.data || [])
const totalFavorites = computed(() => favoritesData.value?.pagination.total || 0)

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
  <VCard id="favorite-list">
    <VCardText>
      <div class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar Favorito"
            style="inline-size: 200px;"
            class="me-3"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
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
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <!-- motor -->
      <template #item.motor="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.raw.imagen_destacada"
            size="38"
            variant="tonal"
            rounded
            :image="getImageBySize(item.raw.imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <NuxtLink :to="`/apps/motors/motor/edit/${item.raw.uuid}`">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.raw.title }}</span>
            </NuxtLink>
            <span class="text-body-2">{{ item.raw.acf.marca.name }}</span>
          </div>
        </div>
      </template>

      <!-- referencia -->
      <template #item.referencia="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.raw.acf.tipo_o_referencia }}</span>
      </template>

      <!-- precio -->
      <template #item.precio="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.raw.acf.precio_de_venta }}</span>
      </template>

      <!-- pagination -->
      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalFavorites"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
