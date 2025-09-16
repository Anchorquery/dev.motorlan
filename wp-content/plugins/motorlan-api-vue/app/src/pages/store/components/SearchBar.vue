<script setup lang="ts">
defineProps<{ loading: boolean; orderOptions: string[] }>()

const emit = defineEmits(['search'])
const searchTerm = defineModel<string>('searchTerm')
const order = defineModel<string | null>('order')
const onSearch = () => emit('search')
</script>

<template>
  <div class="search-bar-wrapper mb-6">
    <h2 class="text-h5 text-error font-weight-bold mb-4">COMPRA VENTA DE MOTORES ELÉCTRICOS INDUSTRIALES</h2>
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
        color="error"
        class="search-btn"
        @click="onSearch"
      >
        BUSCAR
      </VBtn>
      <AppSelect
        v-model="order"
        :items="orderOptions"
        placeholder="Ordenar"
        variant="outlined"
        color="error"
        class="ml-auto"
        style="max-width: 250px;"
      />
    </div>
  </div>
</template>

<style>
.top-bar {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 16px;
}
.search-btn {
  background-color: #da291c !important;
  color: white !important;
}
.search-bar-wrapper .text-error {
  color: #da291c !important;
}
.search-bar-wrapper .v-select--variant-outlined .v-field__outline__color {
  color: #da291c !important;
}
</style>
