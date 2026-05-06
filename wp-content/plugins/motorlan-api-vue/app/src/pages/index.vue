<script setup lang="ts">
import StoreContent from '@/views/store/StoreContent.vue'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="public-store-page">
    <StoreContent />
  </div>
</template>
