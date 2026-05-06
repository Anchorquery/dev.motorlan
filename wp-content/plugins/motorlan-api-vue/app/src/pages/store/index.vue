<script setup lang="ts">
import StoreContent from '@/views/store/StoreContent.vue'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

// Redirigir al dashboard si alguien aterriza aquí
if (typeof window !== 'undefined') {
  const vueBase = (window as any).wpData?.vue_base || '/';
  const cleanBase = vueBase.endsWith('/') ? vueBase : `${vueBase}/`;
  window.location.href = `${cleanBase}dashboard/purchases/purchases`
}
</script>

<template>
  <StoreContent />
</template>
