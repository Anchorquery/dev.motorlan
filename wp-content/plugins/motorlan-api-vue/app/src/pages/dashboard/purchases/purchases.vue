<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { ImagenDestacada } from '@/interfaces/publicacion'

const router = useRouter()

const normalizeStatus = (status: string) => status?.toLowerCase() ?? ''

const headers = [
  { title: 'Publicación', key: 'publicacion' },
  { title: 'Fecha de Compra', key: 'fecha_compra' },
  { title: 'Tipo', key: 'tipo_compra' },
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

// Fetch brand terms to resolve brand name from acf.marca (id or slug)
type BrandTerm = { term_id: number; name: string; slug: string }
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>(createUrl('/wp-json/motorlan/v1/marcas'), { immediate: false }).get().json()
const brands = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [Number(b.term_id), b])))
const brandBySlug = computed<Record<string, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [String(b.slug), b])))

onMounted(() => {
  void fetchBrands()
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

// Helpers to render publication title + specs
const resolveBrandName = (value: any): string | null => {
  if (!value && value !== 0)
    return null
  // Objects like { id, name }
  if (typeof value === 'object') {
    const name = (value?.name || value?.label || value?.title) as string | undefined
    if (name && name.trim().length)
      return name

    const id = Number(value?.id ?? value?.term_id ?? 0) || null
    if (id && brandById.value[id])
      return brandById.value[id].name
  }
  // Numeric id
  const asNum = Number(value)
  if (Number.isFinite(asNum) && asNum > 0 && brandById.value[asNum])
    return brandById.value[asNum].name
  // Slug
  const asStr = String(value)
  if (brandBySlug.value[asStr])
    return brandBySlug.value[asStr].name
  return asStr && asStr !== 'null' && asStr !== 'undefined' ? asStr : null
}

const formatPublicationTitle = (pub: any): string => {
  if (!pub)
    return ''
  const acf = pub.acf || {}
  const parts = [
    pub.title,
    resolveBrandName(acf.marca),
    acf.velocidad ? `${acf.velocidad} rpm` : null,
  ].filter(Boolean) as string[]
  return parts.join(' • ')
}

const resolvePurchaseType = (item: any): { text: string; color: string } => {
  const raw = String(item?.tipo_venta || '').toLowerCase()
  const offerId = Number(item?.offer_id || 0)
  if (offerId)
    return { text: 'Oferta', color: 'info' }
  if (raw === 'rent' || raw === 'alquiler' || raw === 'rental')
    return { text: 'Alquiler', color: 'primary' }
  return { text: 'Directa', color: 'success' }
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
      <!-- Publicación -->
      <template #item.publicacion="{ item }">
        <div
          v-if="item.publicacion || item.motor"
          class="d-flex align-center gap-x-4"
        >
          <VAvatar
            v-if="(item.publicacion || item.motor)?.imagen_destacada"
            size="38"
            variant="tonal"
            rounded
            :image="getImageBySize((item.publicacion || item.motor).imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis cursor-pointer"
              @click="() => {
                const slug = (item.publicacion || item.motor)?.slug
                if (slug)
                  router.push({ name: 'store-slug', params: { slug } })
              }"
            >{{ formatPublicationTitle(item.publicacion || item.motor) }}</span>
            <span
              v-if="(item.publicacion || item.motor)?.acf?.tipo_o_referencia"
              class="text-body-2 text-medium-emphasis"
            >Ref: {{ (item.publicacion || item.motor).acf.tipo_o_referencia }}</span>
          </div>
        </div>
      </template>

      <!-- fecha_compra -->
      <template #item.fecha_compra="{ item }">
        <span class="text-body-1 text-high-emphasis">{{ item.fecha_compra }}</span>
      </template>

      <!-- tipo_compra -->
      <template #item.tipo_compra="{ item }">
        <VChip
          v-bind="resolvePurchaseType(item)"
          density="default"
          label
          size="small"
        />
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
        <IconBtn
          class="me-1"
          @click="router.push(`/dashboard/purchases/${item.uuid}`)"
        >
          <VIcon icon="tabler-eye" />
        </IconBtn>
        <IconBtn
          :disabled="!(item.publicacion || item.motor)?.slug"
          @click="() => {
            const slug = (item.publicacion || item.motor)?.slug
            if (slug) router.push({ name: 'store-slug', params: { slug } })
          }"
        >
          <VIcon icon="tabler-external-link" />
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
