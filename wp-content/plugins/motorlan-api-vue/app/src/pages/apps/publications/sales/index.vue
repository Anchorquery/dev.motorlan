<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()
const router = useRouter()

const searchQuery = ref('')
const selectedType = ref<string>()
const statusFilter = ref('all')
const dateRange = ref('')

const headers = computed(() => [
  { title: t('sales.publication'), key: 'publication_title', sortable: false },
  { title: t('sales.buyer'), key: 'buyer_name', sortable: false },
  { title: t('sales.type'), key: 'type', sortable: false },
  { title: t('sales.price'), key: 'price_value', sortable: true },
  { title: t('sales.date'), key: 'date', sortable: true },
  { title: t('sales.status'), key: 'status', sortable: false },
  { title: t('sales.actions'), key: 'actions', sortable: false },
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
    currency: 'USD',
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

  router.push(`/apps/publications/sales/${sale.uuid}`)
}

const goToPublication = (sale: any) => {
  if (sale?.publication_slug) {
    window.open(`/store/${sale.publication_slug}`, '_blank', 'noopener,noreferrer')
    return
  }

  if (sale?.publication_uuid)
    router.push(`/apps/publications/publication/edit/${sale.publication_uuid}`)
}
</script>

<template>
  <VCard>
    <VCardText>
      <VRow class="gy-4">
        <VCol
          cols="12"
          md="3"
        >
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('sales.search_placeholder')"
            density="comfortable"
            prepend-inner-icon="tabler-search"
          />
        </VCol>
        <VCol
          cols="12"
          md="3"
        >
          <AppSelect
            v-model="selectedType"
            :placeholder="t('sales.type')"
            :items="typeOptions"
            clearable
            clear-icon="tabler-x"
            density="comfortable"
          />
        </VCol>
        <VCol
          cols="12"
          md="3"
        >
          <AppSelect
            v-model="statusFilter"
            :placeholder="t('sales.filters.status')"
            :items="statusOptions"
            density="comfortable"
            clearable
            clear-icon="tabler-x"
            @update:model-value="value => statusFilter = value || 'all'"
          />
        </VCol>
        <VCol
          cols="12"
          md="3"
        >
          <AppDateTimePicker
            v-model="dateRange"
            :placeholder="t('sales.filters.date_range')"
            density="comfortable"
            prepend-inner-icon="tabler-calendar"
            range
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          class="d-flex align-end justify-end"
        >
          <VBtn
            v-if="hasActiveFilters"
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-restore"
            @click="clearFilters"
          >
            {{ t('sales.filters.clear') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDivider class="mt-4" />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="sales"
      :items-length="totalSales"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap"
      item-value="id"
      @update:options="updateOptions"
    >
      <template #item.publication_title="{ item }">
        <div class="d-flex flex-column">
          <RouterLink
            v-if="(item as any).publication_slug"
            :to="`/store/${(item as any).publication_slug}`"
            class="text-primary"
          >
            {{ (item as any).publication_title }}
          </RouterLink>
          <span v-else>
            {{ (item as any).publication_title }}
          </span>
          <small
            v-if="(item as any).publication_slug"
            class="text-caption text-medium-emphasis"
          >
            /store/{{ (item as any).publication_slug }}
          </small>
        </div>
      </template>

      <template #item.buyer_name="{ item }">
        <div>
          <span>{{ (item as any).buyer_name || t('sales.no_buyer') }}</span>
          <div
            v-if="(item as any).buyer_email"
            class="text-caption text-medium-emphasis"
          >
            {{ (item as any).buyer_email }}
          </div>
        </div>
      </template>

      <template #item.type="{ item }">
        {{ resolveType((item as any).type) }}
      </template>

      <template #item.price_value="{ item }">
        {{ formatCurrency((item as any).price_value ?? (item as any).price) }}
      </template>

      <template #item.date="{ item }">
        {{ formatDate((item as any).date, (item as any).date_label) }}
      </template>

      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus((item as any).status)"
          density="comfortable"
          label
          size="small"
        >
          {{ resolveStatus((item as any).status).text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <VBtn
            variant="text"
            color="primary"
            size="small"
            prepend-icon="tabler-eye"
            @click="goToSale(item)"
          >
           
          </VBtn>
          <VBtn
            variant="text"
            color="secondary"
            size="small"
            prepend-icon="tabler-external-link"
            @click="goToPublication(item)"
          >
          
          </VBtn>
        </div>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalSales"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
