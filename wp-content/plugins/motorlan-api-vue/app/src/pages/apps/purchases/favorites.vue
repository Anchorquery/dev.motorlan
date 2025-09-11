<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { ImagenDestacada, Publicacion } from '../../../../../interfaces/publicacion'

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

const { data: favoritesData, execute: fetchFavorites } = await useApi<any>(createUrl('/wp-json/motorlan/v1/favorites', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}))

const favorites = computed((): Publicacion[] => favoritesData.value?.data || [])
const totalFavorites = computed(() => favoritesData.value?.data?.length || 0)

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
    await $api(`/wp-json/motorlan/v1/favorites/${motorId}`, { method: 'DELETE' })
    fetchFavorites()
  }
  catch (error) {
    console.error('Error removing favorite:', error)
  }
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
    title="Mis Favoritos"
  >
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
      <!-- publicacion -->
      <template #item.title="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.imagen_destacada"
            size="38"
            variant="tonal"
            rounded
            :image="getImageBySize(item.imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis cursor-pointer"
              @click="router.push(`/apps/publications/publication/edit/${item.uuid}`)"
            >{{ item.title }}</span>
            <span class="text-body-2">{{ item.acf.marca?.name }}</span>
          </div>
        </div>
      </template>

      <!-- referencia -->
      <template #item.referencia="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.acf.tipo_o_referencia }}</span>
      </template>

      <!-- precio -->
      <template #item.precio="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.acf.precio_de_venta }}</span>
      </template>

      <!-- status -->
      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus(item.status)"
          density="default"
          label
          size="small"
        />
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <IconBtn @click="router.push(`/apps/publications/publication/edit/${item.uuid}`)">
          <VIcon icon="tabler-eye" />
        </IconBtn>
        <IconBtn @click="removeFavorite(item.id)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
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
