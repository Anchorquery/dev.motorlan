<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ImagenDestacada } from '@/interfaces/publicacion'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const headers = [
  { title: 'Publicacion', key: 'publicacion' },
  { title: 'Valoración', key: 'valoracion' },
  { title: 'Comentario', key: 'comentario' },
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

const { data: opinionsData, execute: fetchOpinions } = await useApi<any>(createUrl('/wp-json/motorlan/v1/purchases/opinions', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
}))

const opinions = computed(() => opinionsData.value?.data || [])
const totalOpinions = computed(() => opinionsData.value?.pagination.total || 0)

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
  <Suspense>
    <VCard
      id="opinion-list"
      class="motor-card-enhanced"
    >
      <VCardTitle class="pa-6 pb-0">
        <span class="text-h5 text-premium-title">Mis Opiniones</span>
      </VCardTitle>
      <VCardText class="pa-6">
        <div class="d-flex flex-wrap gap-4">
          <div class="d-flex align-center">
            <!-- 👉 Search  -->
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar Opinión"
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
        :items="opinions"
        :items-length="totalOpinions"
        class="text-no-wrap pb-4"
        @update:options="updateOptions"
      >
        <!-- publicacion -->
        <template #item.publicacion="{ item }: { item: any }">
          <div class="d-flex align-center gap-x-4 py-3">
            <VAvatar
              v-if="item.raw.publicacion?.imagen_destacada"
              size="44"
              variant="tonal"
              rounded
              class="border"
              :image="getImageBySize(item.raw.publicacion.imagen_destacada, 'thumbnail')"
            />
            <div class="d-flex flex-column">
              <NuxtLink :to="`/dashboard/publications/publication/edit/${item.raw.publicacion.uuid}`">
                <span class="text-body-1 font-weight-medium text-premium-title cursor-pointer">{{ item.raw.publicacion.title }}</span>
              </NuxtLink>
              <span class="text-body-2 text-muted">{{ item.raw.publicacion.acf.marca.name }}</span>
            </div>
          </div>
        </template>

        <!-- valoracion -->
        <template #item.valoracion="{ item }: { item: any }">
          <VRating
            :model-value="item.raw.valoracion"
            readonly
            color="warning"
            density="compact"
            size="small"
          />
        </template>

        <!-- comentario -->
        <template #item.comentario="{ item }: { item: any }">
          <span class="text-body-2 text-high-emphasis">{{ item.raw.comentario }}</span>
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
    </VCard>
    <template #fallback>
      <div>
        Cargando...
      </div>
    </template>
  </Suspense>
</template>
