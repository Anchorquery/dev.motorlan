<script setup lang="ts">
import type { Motor } from '@/interfaces/motor'

defineProps<{ motor: Motor }>()
</script>

<template>
  <div class="product-details flex-grow-1">
    <h1 class="text-error mb-2">{{ motor.title }}</h1>
    <div class="text-h5 text-error mb-6">
      {{ motor.acf.precio_de_venta ? motor.acf.precio_de_venta + ' €' : 'Consultar precio' }}
    </div>
    <VTable class="mb-6">
      <tbody>
        <tr>
          <td class="font-weight-medium">Marca</td>
          <td>{{ motor.acf.marca }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">Tipo/Modelo</td>
          <td>{{ motor.acf.tipo_o_referencia }}</td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style scoped>
.product-details h1 {
  font-size: 24px;
}
</style>
