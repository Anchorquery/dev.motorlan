<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const questions = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/questions')
    questions.value = response
  }
  catch (error) {
    console.error('Error fetching questions:', error)
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <VCard title="My Questions">
    <VCardText>
      <VTable
        :headers="[
          { text: 'ID', value: 'id' },
          { text: 'Title', value: 'title' },
          { text: 'Date', value: 'date' },
        ]"
        :items="questions"
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
            v-for="question in questions"
            :key="question.id"
          >
            <td>{{ question.id }}</td>
            <td class="text-center">
              {{ question.title }}
            </td>
            <td class="text-center">
              {{ question.date }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
