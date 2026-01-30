<script setup lang="ts">
import { computed } from 'vue'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{ publicacion?: Publicacion | null }>()
const brandName = computed(() => (props.publicacion as any)?.marca_name || '-')
</script>

<template>
  <VCard v-if="publicacion" class="publicacion-info" flat>
    <VCardTitle class="info-title d-flex align-center">
      <VIcon icon="tabler-info-circle" color="error" class="mr-2" />
      Información de la publicación
    </VCardTitle>
    <VCardText class="pt-0">
      <VTable class="info-table">
        <tbody>
          <tr>
            <td class="font-weight-medium">
              Marca
            </td>
            <td>{{ brandName }}</td>
          </tr>
          <tr>
            <td class="font-weight-medium">
              Modelo
            </td>
            <td>{{ publicacion.acf?.tipo_o_referencia || '-' }}</td>
          </tr>
        <tr>
          <td class="font-weight-medium">
            Potencia
          </td>
          <td>{{ publicacion.acf?.potencia ? `${publicacion.acf.potencia} kW` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Velocidad
          </td>
          <td>{{ publicacion.acf?.velocidad ? `${publicacion.acf.velocidad} rpm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Par nominal
          </td>
          <td>{{ publicacion.acf?.par_nominal ? `${publicacion.acf.par_nominal} Nm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Voltaje
          </td>
          <td>{{ publicacion.acf?.voltaje ? `${publicacion.acf.voltaje} V` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Intensidad
          </td>
          <td>{{ publicacion.acf?.intensidad ? `${publicacion.acf.intensidad} A` : '-' }}</td>
        </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>

<style scoped>
.publicacion-info {
  border: 1px solid #E6E6E6;
  border-radius: 12px;
  height: 100%;
  width: 100%;
  flex: 1 1 auto;
  background: #ffffff;
  box-shadow: 0 12px 24px rgba(20, 20, 43, 0.06);
}
.info-title {
  font-weight: 600;
  padding: 24px 24px 16px;
}
.info-title .v-icon {
  background: rgba(var(--v-theme-error), 0.1);
  border-radius: 8px;
  padding: 8px;
  width: 32px;
  height: 32px;
}
.info-table td {
  padding-block: 8px;
}
.info-table tr + tr td {
  border-top: 1px solid rgba(15, 23, 42, 0.06);
}
</style>
