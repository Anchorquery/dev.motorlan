<script setup lang="ts">
import type { Favorite } from '../../../../interfaces/favorite'

const headers = [
  { title: 'Favorite ID', key: 'id' },
  { title: 'Motor', key: 'title' },
  { title: 'Date Added', key: 'date' },
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

const { data: favoritesData, execute: fetchFavorites } = await useApi<any>(createUrl('/wp-json/motorlan/v1/favorites',
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

const favorites = computed((): Favorite[] => favoritesData.value?.data || [])
const totalFavorites = computed(() => favoritesData.value?.pagination.total || 0)

const removeFavorite = (id: number) => {
  // Here you would typically call an API endpoint to remove the favorite
  console.log(`Removing favorite with id: ${id}`)
}
</script>

<template>
  <div>
    <VCard
      title="My Favorites"
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
              placeholder="Search Favorite"
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
        :items="favorites"
        :items-length="totalFavorites"
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

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="$router.push(`/apps/motors/motor/edit/${item.acf.motor_uuid}`)">
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
  </div>
</template>
