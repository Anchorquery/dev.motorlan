<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()
const router = useRouter()

const headers = [
  { title: t('sales.publication'), key: 'publication_title' },
  { title: t('sales.price'), key: 'price' },
  { title: t('sales.date'), key: 'date' },
]

const searchQuery = ref('')
const selectedType = ref()

const typeOptions = computed(() => [
  { title: t('sales.type_options.sale'), value: 'sale' },
  { title: t('sales.type_options.rent'), value: 'rent' },
])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (selectedType.value)
    params.append('type', selectedType.value)
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

const { data: salesData, execute: fetchSales, isLoading: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchSales()
  isSearching.value = false
}, 300)

watch(
  [searchQuery, selectedType, page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

onMounted(() => {
  fetchSales()
})

const sales = computed(() => salesData.value?.data || [])
const totalSales = computed(() => salesData.value?.pagination?.total || 0)
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6">
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('sales.search_placeholder')"
            style="inline-size: 200px;"
            class="me-3"
          />
        </VCol>
        <VCol cols="12" sm="6">
          <AppSelect
            v-model="selectedType"
            :placeholder="t('sales.type')"
            :items="typeOptions"
            clearable
            clear-icon="tabler-x"
          />
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
        <router-link :to="`/store/${(item as any).publication_slug}`">
          {{ (item as any).publication_title }}
        </router-link>
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