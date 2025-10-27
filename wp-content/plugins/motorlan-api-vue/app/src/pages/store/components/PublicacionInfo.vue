<script setup lang="ts">
import type { Publicacion } from '@/interfaces/publicacion'

defineProps<{ publicacion: Publicacion }>()
const emit = defineEmits(['open-chat'])
</script>

<template>
  <div class="publicacion-info">
    <div class="seller-info">
      <VAvatar
        size="48"
        class="mr-4"
      >
        <VImg
          :src="publicacion.author.avatar"
          :alt="publicacion.author.name"
        />
      </VAvatar>
      <div>
        <p class="font-weight-bold">
          {{ publicacion.author.name }}
        </p>
        <p class="text-caption">
          {{ publicacion.author.meta.sales }} ventas | {{ publicacion.author.meta.rating }} valoraciones
        </p>
      </div>
      <VBtn
        class="ml-auto"
        variant="outlined"
        @click="emit('open-chat')"
      >
        Chat
      </VBtn>
    </div>
    <VDivider class="my-4" />
    <h3 class="mb-4">
      Información de la publicacion
    </h3>
    <VTable>
      <tbody>
        <tr>
          <td class="font-weight-medium">
            Marca
          </td>
          <td>{{ publicacion.acf.marca || '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Potencia
          </td>
          <td>{{ publicacion.acf.potencia ? `${publicacion.acf.potencia} kW` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Velocidad
          </td>
          <td>{{ publicacion.acf.velocidad ? `${publicacion.acf.velocidad} rpm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Par nominal
          </td>
          <td>{{ publicacion.acf.par_nominal ? `${publicacion.acf.par_nominal} Nm` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Voltaje
          </td>
          <td>{{ publicacion.acf.voltaje ? `${publicacion.acf.voltaje} V` : '-' }}</td>
        </tr>
        <tr>
          <td class="font-weight-medium">
            Intensidad
          </td>
          <td>{{ publicacion.acf.intensidad ? `${publicacion.acf.intensidad} A` : '-' }}</td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style scoped>
.publicacion-info {
  flex: 1 1 300px;
}
.seller-info {
  display: flex;
  align-items: center;
}
</style>
