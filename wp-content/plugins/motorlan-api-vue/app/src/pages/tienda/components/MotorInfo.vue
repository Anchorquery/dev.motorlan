<script setup lang="ts">
import type { Motor } from '@/interfaces/motor'

defineProps<{ motor: Motor }>()
</script>

<template>
  <div class="motor-info">
    <h3 class="text-error mb-4">
      Información del motor
    </h3>
    <VTable>
      <tbody>
        <tr>
          <td class="font-weight-medium">
            Marca
          </td>
          <td>{{ motor.acf.marca?.name || motor.acf.marca || '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Potencia
          </td>
          <td>{{ motor.acf.potencia ? `${motor.acf.potencia} kW` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Velocidad
          </td>
          <td>{{ motor.acf.velocidad ? `${motor.acf.velocidad} rpm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Par nominal
          </td>
          <td>{{ motor.acf.par_nominal ? `${motor.acf.par_nominal} Nm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Voltaje
          </td>
          <td>{{ motor.acf.voltaje ? `${motor.acf.voltaje} V` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Intensidad
          </td>
          <td>{{ motor.acf.intensidad ? `${motor.acf.intensidad} A` : '-' }}</td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style scoped>
.motor-info {
  flex: 1 1 300px;
}
</style>
