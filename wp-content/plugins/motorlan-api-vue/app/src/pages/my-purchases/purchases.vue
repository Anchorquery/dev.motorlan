<script setup lang="ts">
import type { Purchase } from '../../../../interfaces/purchase'

const headers = [
  { title: 'Purchase ID', key: 'id' },
  { title: 'Motor', key: 'title' },
  { title: 'Date', key: 'date' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
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
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const { data: purchasesData, execute: fetchPurchases } = await useApi<any>(createUrl('/wp-json/motorlan/v1/purchases',
  {
    query: {
      search: searchQuery,
      page,
      per_page: itemsPerPage,
      orderby: sortBy,
      order: orderBy,
    },
  },
))

const purchases = computed((): Purchase[] => purchasesData.value?.data || [])
const totalPurchases = computed(() => purchasesData.value?.pagination.total || 0)

const resolveStatus = (status: string) => {
  if (status === 'publish')
    return { text: 'Completed', color: 'success' }
  if (status === 'pending')
    return { text: 'Pending', color: 'warning' }
  if (status === 'failed')
    return { text: 'Failed', color: 'error' }

  return { text: 'Unknown', color: 'info' }
}
</script>

<template>
  <div>
    <VCard
      title="My Purchases"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Purchase"
              style="inline-size: 200px;"
              class="me-3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- 👉 Datatable  -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        show-select
        :items="purchases"
        :items-length="totalPurchases"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- motor  -->
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-x-4">
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.title }}</span>
            </div>
          </div>
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
          <IconBtn @click="$router.push(`/apps/invoice/preview/${item.id}`)">
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
  </div>
</template>
