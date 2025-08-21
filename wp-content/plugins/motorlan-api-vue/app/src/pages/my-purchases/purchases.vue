<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

const purchases = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/purchases')
    purchases.value = response
  }
  catch (error) {
    console.error('Error fetching purchases:', error)
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <VCard title="My Purchases">
    <VCardText>
      <VTable
        :headers="[
          { text: 'ID', value: 'id' },
          { text: 'Title', value: 'title' },
          { text: 'Date', value: 'date' },
        ]"
        :items="purchases"
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
            v-for="purchase in purchases"
            :key="purchase.id"
          >
            <td>{{ purchase.id }}</td>
            <td class="text-center">
              {{ purchase.title }}
            </td>
            <td class="text-center">
              {{ purchase.date }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
