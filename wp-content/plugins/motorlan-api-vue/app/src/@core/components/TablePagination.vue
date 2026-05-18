<script setup lang="ts">
import { paginationMeta } from '@/utils/paginationMeta'
interface Props {
  page: number
  itemsPerPage: number
  totalItems: number
}

interface Emit {
  (e: 'update:page', value: number): void
}

defineProps<Props>()

const emit = defineEmits<Emit>()

const updatePage = (value: number) => {
  emit('update:page', value)
}
</script>

<template>
  <div class="table-pagination-shell">
    <VDivider />

    <div class="table-pagination-content d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 px-4 px-sm-6 py-3">
      <p class="table-pagination-meta text-disabled mb-0">
        {{ paginationMeta({ page, itemsPerPage }, totalItems) }}
      </p>

      <VPagination
        :model-value="page"
        active-color="primary"
        :length="Math.ceil(totalItems / itemsPerPage)"
        :size="$vuetify.display.smAndDown ? 'small' : 'default'"
        :total-visible="$vuetify.display.smAndDown ? 3 : Math.min(Math.ceil(totalItems / itemsPerPage), 5)"
        @update:model-value="updatePage"
      />
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 599px) {
  .table-pagination-content {
    flex-direction: column;
    align-items: stretch;
  }

  .table-pagination-meta {
    text-align: center;
  }

  .table-pagination-content :deep(.v-pagination) {
    justify-content: center;
  }
}
</style>
