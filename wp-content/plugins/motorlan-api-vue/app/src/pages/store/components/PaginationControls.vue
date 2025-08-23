<script setup lang="ts">
const page = defineModel<number>('page')

defineProps<{ totalPages: number }>()
</script>

<template>
  <VPagination
    v-if="totalPages > 1"
    v-model="page"
    :length="totalPages"
    :total-visible="5"
    class="mt-6"
  />
</template>

