<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import type { ImagenDestacada } from '@/interfaces/publicacion'

const { t } = useI18n()
const router = useRouter()

const searchQuery = ref('')
const selectedType = ref<string>()
const statusFilter = ref('all')
const dateRange = ref('')

const headers = computed(() => [
  { title: t('sales.publication'), value: 'publication_title', sortable: false },
  { title: t('sales.buyer'), value: 'buyer_name', sortable: false },
  { title: t('sales.type'), value: 'type', sortable: false },
  { title: t('sales.price'), value: 'price_value', sortable: true },
  { title: t('sales.date'), value: 'date', sortable: true },
  { title: t('sales.status'), value: 'status', sortable: false },
  { title: t('sales.actions'), value: 'actions', sortable: false },
])

const typeOptions = computed(() => [
  { title: t('sales.type_options.sale'), value: 'sale' },
  { title: t('sales.type_options.rent'), value: 'rent' },
])

const statusOptions = computed(() => [
  { title: t('sales.status_options.all'), value: 'all' },
  { title: t('sales.status_options.completed'), value: 'completed' },
  { title: t('sales.status_options.pending'), value: 'pendiente' },
  { title: t('sales.status_options.processing'), value: 'processing' },
  { title: t('sales.status_options.cancelled'), value: 'cancelled' },
  { title: t('sales.status_options.refunded'), value: 'refunded' },
  { title: t('sales.status_options.expired'), value: 'expired' },
])

const dateRangeParams = computed(() => {
  if (!dateRange.value)
    return { from: '', to: '' }

  const [from, to] = dateRange.value.split(' to ')

  return {
    from: from || '',
    to: (to || from) || '',
  }
})

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const formatCurrency = (value: number | string | null | undefined) => {
  const numericValue = Number(value)
  if (Number.isNaN(numericValue))
    return value ?? '—'

  return new Intl.NumberFormat('es-VE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numericValue)
}

const formatDate = (value: string, fallback?: string) => {
  if (!value) {
    if (!fallback)
      return '—'
    return fallback
  }

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime()))
    return fallback || value

  return parsed.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const resolveStatus = (status: string) => {
  const normalized = (status || '').toLowerCase()
  if (normalized === 'completed')
    return { text: t('sales.status_labels.completed'), color: 'success' }
  if (normalized === 'pendiente' || normalized === 'pending')
    return { text: t('sales.status_labels.pending'), color: 'warning' }
  if (normalized === 'processing')
    return { text: t('sales.status_labels.processing'), color: 'info' }
  if (normalized === 'cancelled' || normalized === 'canceled')
    return { text: t('sales.status_labels.cancelled'), color: 'error' }
  if (normalized === 'refunded')
    return { text: t('sales.status_labels.refunded'), color: 'secondary' }
  if (normalized === 'expired')
    return { text: t('sales.status_labels.expired'), color: 'warning' }

  if (!status)
    return { text: t('sales.status_labels.unknown'), color: 'secondary' }

  return { text: status.toUpperCase(), color: 'primary' }
}

const resolveType = (type: string) => {
  if ((type || '').toLowerCase() === 'rent')
    return t('sales.type_options.rent')

  return t('sales.type_options.sale')
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedType.value = undefined
  statusFilter.value = 'all'
  dateRange.value = ''
}

const hasActiveFilters = computed(() => {
  const isStatusFiltered = statusFilter.value && statusFilter.value !== 'all'

  return Boolean(
    searchQuery.value
    || selectedType.value
    || isStatusFiltered
    || dateRangeParams.value.from
    || dateRangeParams.value.to,
  )
})

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (selectedType.value)
    params.append('type', selectedType.value)
  if (statusFilter.value)
    params.append('status', statusFilter.value)
  if (dateRangeParams.value.from)
    params.append('date_from', dateRangeParams.value.from)
  if (dateRangeParams.value.to)
    params.append('date_to', dateRangeParams.value.to)
  if (page.value)
    params.append('page', page.value.toString())
  if (itemsPerPage.value)
    params.append('per_page', itemsPerPage.value.toString())
  if (sortBy.value)
    params.append('orderby', sortBy.value)
  if (orderBy.value)
    params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/user/sales?${params.toString()}`
})

const { data: salesData, execute: fetchSales, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchSales()
  isSearching.value = false
}, 300)

watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

watch(
  [searchQuery, selectedType, statusFilter, dateRange],
  () => {
    if (page.value !== 1)
      page.value = 1
    else
      debouncedFetch()
  },
  { deep: true },
)

onMounted(() => {
  fetchSales()
})

const sales = computed(() => salesData.value?.data || [])
const totalSales = computed(() => salesData.value?.pagination?.total || 0)

const goToSale = (sale: any) => {
  if (!sale?.uuid)
    return

  router.push(`/dashboard/publications/sales/${sale.uuid}`)
}

const goToPublication = (sale: any) => {
  const slug = (sale?.publicacion?.slug) || sale?.publication_slug || sale?.motor_slug
  if (slug) {
    window.open(`/${slug}` , '_blank', 'noopener,noreferrer')
    return
  }
  if (sale?.publication_uuid)
    router.push(`/dashboard/publications/publication/edit/${sale.publication_uuid}`)
}

// Brands catalog to resolve acf.marca -> name
type BrandTerm = { term_id: number; name: string; slug: string }
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
const brands = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [Number(b.term_id), b])))
const brandBySlug = computed<Record<string, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [String(b.slug), b])))

onMounted(() => { void fetchBrands() })

const resolveBrandName = (value: any): string | null => {
  if (!value && value !== 0)
    return null
  if (typeof value === 'object') {
    const name = (value?.name || value?.label || value?.title) as string | undefined
    if (name && name.trim().length)
      return name
    const id = Number(value?.id ?? value?.term_id ?? 0) || null
    if (id && brandById.value[id])
      return brandById.value[id].name
  }
  const asNum = Number(value)
  if (Number.isFinite(asNum) && asNum > 0 && brandById.value[asNum])
    return brandById.value[asNum].name
  const asStr = String(value)
  if (brandBySlug.value[asStr])
    return brandBySlug.value[asStr].name
  return asStr && asStr !== 'null' && asStr !== 'undefined' ? asStr : null
}

const getImageBySize = (image: ImagenDestacada | null | any[], size = 'thumbnail'): string => {
  let imageObj: ImagenDestacada | null = null
  if (Array.isArray(image) && image.length > 0)
    imageObj = image[0] as ImagenDestacada
  else if (image && !Array.isArray(image))
    imageObj = image as ImagenDestacada
  if (!imageObj)
    return ''
  if ((imageObj as any).sizes && (imageObj as any).sizes[size])
    return (imageObj as any).sizes[size] as string
  return (imageObj as any).url || ''
}

const formatPublicationTitle = (pub: any, fallbackTitle?: string): string => {
  if (!pub) {
    return fallbackTitle || ''
  }
  const acf = pub.acf || {}
  const parts = [
    pub.title || fallbackTitle,
    resolveBrandName(acf.marca),
    acf.velocidad ? `${acf.velocidad} rpm` : null,
  ].filter(Boolean) as string[]
  return parts.join(' • ')
}
</script>

<template>
  <VCard class="motor-card-enhanced overflow-visible">
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">{{ t('Ventas Realizadas') }}</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <VRow>
        <VCol cols="12" md="3">
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('sales.search_placeholder')"
            prepend-inner-icon="tabler-search"
            class="elevation-0"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppSelect
            v-model="selectedType"
            :items="typeOptions"
            :placeholder="t('sales.type')"
            prepend-inner-icon="tabler-category"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppSelect
            v-model="statusFilter"
            :items="statusOptions"
            :placeholder="t('sales.filters.status')"
            prepend-inner-icon="tabler-filter"
            clearable
            clear-icon="tabler-x"
            @update:model-value="(value: string | null) => statusFilter = value || 'all'"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppDateTimePicker
            v-model="dateRange"
            :placeholder="t('sales.filters.date_range')"
            prepend-inner-icon="tabler-calendar"
            :config="{ mode: 'range' }"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
      <div v-if="hasActiveFilters" class="d-flex justify-end mt-4">
        <VBtn
          color="secondary"
          variant="tonal"
          size="small"
          prepend-icon="tabler-restore"
          class="rounded-pill"
          @click="clearFilters"
        >
          {{ t('sales.filters.clear') }}
        </VBtn>
      </div>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="sales"
      :items-length="totalSales"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap px-6 pb-6"
      item-value="id"
      @update:options="updateOptions"
    >
      <template #item.publication_title="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar
            v-if="((item as any).publicacion || (item as any).motor)?.imagen_destacada"
            size="48"
            variant="tonal"
            rounded
            class="border"
            :image="getImageBySize(((item as any).publicacion || (item as any).motor).imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <template v-if="((item as any).publicacion || (item as any).motor)?.slug || (item as any).publication_slug || (item as any).motor_slug">
              <RouterLink
                :to="`/${((item as any).publicacion || (item as any).motor)?.slug || (item as any).publication_slug || (item as any).motor_slug}`"
                class="text-body-1 font-weight-medium text-high-emphasis text-decoration-none"
              >
                {{ formatPublicationTitle(((item as any).publicacion || (item as any).motor), (item as any).publication_title || (item as any).motor_title) }}
              </RouterLink>
            </template>
            <span v-else class="text-body-1 font-weight-medium text-high-emphasis">
              {{ formatPublicationTitle(((item as any).publicacion || (item as any).motor), (item as any).publication_title || (item as any).motor_title) }}
            </span>
            <span
              v-if="((item as any).publicacion || (item as any).motor)?.acf?.tipo_o_referencia"
              class="text-caption text-medium-emphasis"
            >
              Ref: {{ ((item as any).publicacion || (item as any).motor).acf.tipo_o_referencia }}
            </span>
          </div>
        </div>
      </template>

      <template #item.buyer_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-2 font-weight-medium text-high-emphasis">{{ (item as any).buyer_name || t('sales.no_buyer') }}</span>
          <span
            v-if="(item as any).buyer_email"
            class="text-caption text-medium-emphasis"
          >
            {{ (item as any).buyer_email }}
          </span>
        </div>
      </template>

      <template #item.type="{ item }">
        <VChip
          size="small"
          variant="tonal"
          class="text-uppercase font-weight-bold"
          :color="(item as any).type === 'rent' ? 'info' : 'success'"
          label
        >
          {{ resolveType((item as any).type) }}
        </VChip>
      </template>

      <template #item.price_value="{ item }">
        <span class="text-body-1 font-weight-bold text-high-emphasis">
          {{ formatCurrency((item as any).price_value ?? (item as any).price) }}
        </span>
      </template>

      <template #item.date="{ item }">
        <span class="text-body-2 text-medium-emphasis">
          {{ formatDate((item as any).date, (item as any).date_label) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus((item as any).status)"
          density="comfortable"
          label
          size="small"
          class="font-weight-medium"
        />
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-2">
          <IconBtn
            variant="tonal"
            color="primary"
            size="small"
            @click="goToSale(item)"
          >
            <VIcon icon="tabler-eye" size="18" />
            <VTooltip activator="parent" location="top">Ver detalle</VTooltip>
          </IconBtn>
          <IconBtn
            variant="tonal"
            color="secondary"
            size="small"
            @click="goToPublication(item)"
          >
            <VIcon icon="tabler-external-link" size="18" />
            <VTooltip activator="parent" location="top">Ver publicación</VTooltip>
          </IconBtn>
        </div>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalSales"
          class="mt-4"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
