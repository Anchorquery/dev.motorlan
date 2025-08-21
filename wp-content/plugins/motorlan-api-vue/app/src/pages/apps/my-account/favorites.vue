<script setup lang="ts">
import { useUser } from '~/composables/useUser'

const { user } = useUser()

const { data: favorites, pending, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/my-account/favorites'))

const headers = [
  { title: 'Motor', key: 'motor.title' },
]
</script>

<template>
  <VCard>
    <VCardTitle>Mis Favoritos</VCardTitle>
    <VCardText>
      <VDataTable
        :headers="headers"
        :items="favorites"
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
            No tienes motores favoritos.
          </p>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>
</template>
