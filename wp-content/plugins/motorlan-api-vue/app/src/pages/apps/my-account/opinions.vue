<script setup lang="ts">
const { data: opinions, pending, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/my-account/opinions'))

const headers = [
  { title: 'Motor', key: 'motor.title' },
  { title: 'Valoración', key: 'valoracion' },
  { title: 'Comentario', key: 'comentario' },
]
</script>

<template>
  <VCard>
    <VCardTitle>Mis Opiniones</VCardTitle>
    <VCardText>
      <VDataTable
        :headers="headers"
        :items="opinions"
        :loading="pending"
        class="elevation-1"
      >
        <template #item.motor.title="{ item }">
          <NuxtLink :to="`/apps/motors/motor/edit/${item.raw.motor.uuid}`">
            {{ item.raw.motor.title }}
          </NuxtLink>
        </template>
        <template #item.valoracion="{ item }">
          <VRating
            :model-value="item.raw.valoracion"
            readonly
            dense
            size="small"
          />
        </template>
        <template #loading>
          <VSkeletonLoader type="table-row@10" />
        </template>
        <template #no-data>
          <p class="text-center">
            No has realizado ninguna opinión.
          </p>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>
</template>
