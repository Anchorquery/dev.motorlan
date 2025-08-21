<script setup lang="ts">
import { useUser } from '~/composables/useUser'

const { user } = useUser()

const { data: questions, pending, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/my-account/questions'))

const headers = [
  { title: 'Motor', key: 'motor.title' },
  { title: 'Pregunta', key: 'pregunta' },
  { title: 'Respuesta', key: 'respuesta' },
]
</script>

<template>
  <VCard>
    <VCardTitle>Mis Preguntas</VCardTitle>
    <VCardText>
      <VDataTable
        :headers="headers"
        :items="questions"
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
            No has realizado ninguna pregunta.
          </p>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>
</template>
