<script setup lang="ts">
const { data: purchases, pending, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/my-account/purchases'))

const headers = [
  { title: 'Motor', key: 'motor.title' },
  { title: 'Fecha de Compra', key: 'fecha_compra' },
]
</script>

<template>
  <VCard>
    <VCardTitle>Mis Compras</VCardTitle>
    <VCardText>
      <VDataTable
        :headers="headers"
        :items="purchases"
        :loading="pending"
        class="elevation-1"
      >
        <template #item.motor.title="{ item }">
          <NuxtLink :to="`/apps/motors/motor/edit/${item.raw.motor.uuid}`">
            {{ item.raw.motor.title }}
          </NuxtLink>
        </template>
        <template #loading>
          <VSkeletonLoader type="table-row@10" />
        </template>
        <template #no-data>
          <p class="text-center">
            No has realizado ninguna compra.
          </p>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>
</template>
