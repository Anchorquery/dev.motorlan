<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const uuid = route.params.uuid

onMounted(() => {
  // Redirigir a la vista de detalle de compra abriendo el modal de chat automáticamente
  router.replace({
    path: `/dashboard/purchases/${uuid}`,
    query: { openChat: 'true' }
  })
})
</script>

<template>
  <div class="d-flex justify-center align-center h-screen">
    <VProgressCircular indeterminate color="primary" size="64" />
    <span class="sr-only">Redirigiendo al chat...</span>
  </div>
</template>
