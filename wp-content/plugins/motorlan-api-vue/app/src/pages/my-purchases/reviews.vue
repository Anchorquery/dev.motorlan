<script setup lang="ts">
import type { Review } from '../../../../interfaces/review'

const headers = [
  { title: 'Review ID', key: 'id' },
  { title: 'Motor', key: 'title' },
  { title: 'Date', key: 'date' },
  { title: 'Rating', key: 'rating' },
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

const { data: reviewsData, execute: fetchReviews } = await useApi<any>(createUrl('/wp-json/motorlan/v1/reviews',
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

const reviews = computed((): Review[] => reviewsData.value?.data || [])
const totalReviews = computed(() => reviewsData.value?.pagination.total || 0)

const getRating = (review: Review) => {
  // Assuming rating is stored in acf fields
  return review.acf?.rating || 0
}
</script>

<template>
  <div>
    <VCard
      title="My Reviews"
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
              placeholder="Search Review"
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
        :items="reviews"
        :items-length="totalReviews"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- title  -->
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-x-4">
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.title }}</span>
            </div>
          </div>
        </template>

        <!-- rating -->
        <template #item.rating="{ item }">
          <VRating
            :model-value="getRating(item)"
            readonly
            half-increments
            dense
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
            :total-items="totalReviews"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
