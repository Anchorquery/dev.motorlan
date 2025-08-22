<script setup lang="ts">
defineProps<{ loading: boolean; orderOptions: string[] }>()

const emit = defineEmits(['search'])
const searchTerm = defineModel<string>('searchTerm')
const order = defineModel<string | null>('order')
const onSearch = () => emit('search')
</script>

<template>
  <div class="top-bar">
    <VTextField
      v-model="searchTerm"
      placeholder="Buscar..."
      variant="outlined"
      hide-details
      class="flex-grow-1"
      @keydown.enter="onSearch"
    />
    <VBtn
      icon
      color="error"
      class="search-btn"
      :loading="loading"
      @click="onSearch"
    >
      <VIcon color="white">
        mdi-magnify
      </VIcon>
    </VBtn>
    <AppSelect

      v-model="order"
      :items="orderOptions"
      label=""
      max-width="250"
    />
  </div>
</template>

<style scoped>
.top-bar {
  display: flex;
  align-items: flex-start;
  width: 100%;
  gap: 16px;
  margin-bottom: 24px;
}
.search-btn {
  height: 56px;
  width: 56px;
}
</style>
