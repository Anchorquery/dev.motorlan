<script setup lang="ts">
import type { Question } from '../../../../interfaces/question'

const headers = [
  { title: 'Question ID', key: 'id' },
  { title: 'Question', key: 'title' },
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

const { data: questionsData, execute: fetchQuestions } = await useApi<any>(createUrl('/wp-json/motorlan/v1/questions',
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

const questions = computed((): Question[] => questionsData.value?.data || [])
const totalQuestions = computed(() => questionsData.value?.pagination.total || 0)

const resolveStatus = (status: string) => {
  if (status === 'publish')
    return { text: 'Answered', color: 'success' }
  if (status === 'pending')
    return { text: 'Pending', color: 'warning' }

  return { text: 'Unknown', color: 'info' }
}
</script>

<template>
  <div>
    <VCard
      title="My Questions"
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
              placeholder="Search Question"
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
        :items="questions"
        :items-length="totalQuestions"
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
          <IconBtn @click="$router.push(`/apps/chat?question_id=${item.id}`)">
            <VIcon icon="tabler-message-circle" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalQuestions"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
