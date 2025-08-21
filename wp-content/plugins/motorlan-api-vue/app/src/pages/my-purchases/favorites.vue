<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const favorites = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/favorites')
    favorites.value = response
  }
  catch (error) {
    console.error('Error fetching favorites:', error)
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <VCard title="My Favorites">
    <VCardText>
      <VTable
        :headers="[
          { text: 'ID', value: 'id' },
          { text: 'Title', value: 'title' },
          { text: 'Date', value: 'date' },
        ]"
        :items="favorites"
        :loading="loading"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th class="text-uppercase">
              ID
            </th>
            <th class="text-uppercase text-center">
              Title
            </th>
            <th class="text-uppercase text-center">
              Date
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="favorite in favorites"
            :key="favorite.id"
          >
            <td>{{ favorite.id }}</td>
            <td class="text-center">
              {{ favorite.title }}
            </td>
            <td class="text-center">
              {{ favorite.date }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
