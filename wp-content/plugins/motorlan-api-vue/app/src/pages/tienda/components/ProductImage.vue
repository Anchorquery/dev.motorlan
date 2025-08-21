<script setup lang="ts">
import type { Motor } from '@/interfaces/motor'

defineProps<{ motor: Motor }>()
</script>

<template>
  <div class="product-image">
    <img :src="motor.imagen_destacada?.url || '/placeholder.png'" alt="" />
  </div>
</template>

<style scoped>
.product-image {
  flex: 1 1 300px;
  max-width: 600px;
  background: #EEF1F4;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
}
.product-image img {
  max-width: 100%;
  max-height: 100%;
}
</style>
