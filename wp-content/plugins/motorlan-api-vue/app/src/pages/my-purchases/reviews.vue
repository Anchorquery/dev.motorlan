<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const reviews = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/reviews')
    reviews.value = response
  }
  catch (error) {
    console.error('Error fetching reviews:', error)
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <VCard title="My Reviews">
    <VCardText>
      <VTable
        :headers="[
          { text: 'ID', value: 'id' },
          { text: 'Title', value: 'title' },
          { text: 'Date', value: 'date' },
        ]"
        :items="reviews"
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
            v-for="review in reviews"
            :key="review.id"
          >
            <td>{{ review.id }}</td>
            <td class="text-center">
              {{ review.title }}
            </td>
            <td class="text-center">
              {{ review.date }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
