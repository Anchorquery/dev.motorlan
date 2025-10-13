<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { ImagenDestacada } from '../../../../../interfaces/publicacion'

const router = useRouter()

const normalizeStatus = (status: string) => status?.toLowerCase() ?? ''

const headers = [
  { title: 'Publicacion', key: 'motor' },
  { title: 'Fecha de Compra', key: 'fecha_compra' },
  { title: 'Estado', key: 'estado' },
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

const resolveStatus = (status: string) => {
  switch (normalizeStatus(status)) {
    case 'pending':
    case 'pendiente':
      return { text: 'Pendiente', color: 'warning' }
    case 'completed':
    case 'completado':
      return { text: 'Completado', color: 'success' }
    case 'cancelled':
    case 'cancelado':
      return { text: 'Cancelado', color: 'error' }
    case 'rejected':
      return { text: 'Rechazado', color: 'error' }
    default:
      return { text: status || 'Desconocido', color: 'info' }
  }
}

const purchasesApiUrl = createUrl('/wp-json/motorlan/v1/purchases/purchases', {
  query: {
    page,
    per_page: itemsPerPage,
    orderby: sortBy,
    order: orderBy,
    search: searchQuery,
  },
})

const {
  data: purchasesData,
  execute: fetchPurchases,
  isFetching: isTableLoading,
} = useApi<any>(purchasesApiUrl, { immediate: false }).get().json()

watch(purchasesApiUrl, () => {
  fetchPurchases()
}, { immediate: true })

watch(searchQuery, () => {
  if (page.value !== 1)
    page.value = 1
})

const purchases = computed(() => {
  const raw = purchasesData.value?.data ?? purchasesData.value ?? []
  return Array.isArray(raw) ? raw.filter(Boolean) : []
})

const totalPurchases = computed(() => {
  if (purchasesData.value?.pagination?.total != null)
    return purchasesData.value.pagination.total
  if (typeof purchasesData.value?.total === 'number')
    return purchasesData.value.total
  if (Array.isArray(purchasesData.value?.data))
    return purchasesData.value.data.length
  const raw = purchasesData.value
  if (Array.isArray(raw))
    return raw.length
  return 0
})

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
    id="purchase-list"
    title="Mis Compras"
  >
    <VCardText>
      <div class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar Compra"
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
      :items="purchases"
      :items-length="totalPurchases"
      :loading="isTableLoading"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <!-- publicacion -->
      <template #item.motor="{ item }">
        <div
          v-if="item.motor"
          class="d-flex align-center gap-x-4"
        >
          <VAvatar
            v-if="item.motor.imagen_destacada"
            size="38"
            variant="tonal"
            rounded
            :image="getImageBySize(item.motor.imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis cursor-pointer"
              @click="router.push(`/apps/publications/publication/edit/${item.motor.uuid}`)"
            >{{ item.motor.title }}</span>
            <span class="text-body-2">{{ item.motor.acf.marca?.name }}</span>
          </div>
        </div>
      </template>

      <!-- fecha_compra -->
      <template #item.fecha_compra="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.fecha_compra }}</span>
      </template>

      <!-- estado -->
      <template #item.estado="{ item }">
        <VChip
          v-bind="resolveStatus(item.estado)"
          density="default"
          label
          size="small"
        />
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <IconBtn @click="router.push(`/apps/purchases/${item.uuid}`)">
          <VIcon icon="tabler-eye" />
        </IconBtn>
      </template>

      <!-- pagination -->
      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalPurchases"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
