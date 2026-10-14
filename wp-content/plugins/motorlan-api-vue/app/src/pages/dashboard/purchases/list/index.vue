<script setup lang="ts">
// Página antigua de compras: hacía GET /purchases (no existe, 404) y enlazaba a
// /dashboard/purchases/edit/{uuid} (tampoco existe). Se conserva solo como redirección
// a "Mis compras" por si queda algún enlace guardado. Se puede borrar el archivo.
const router = useRouter()

router.replace({ name: 'dashboard-purchases-purchases' })
</script>

<template>
  <div />
</template>
