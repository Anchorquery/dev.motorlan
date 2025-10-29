<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useRouter } from 'vue-router'

interface InquiryItem {
  product_id: number
  product_title: string
  product_slug: string
  room_key: string
  last_at: string
}

const isLoading = ref(false)
const loadError = ref<string | null>(null)
const items = ref<InquiryItem[]>([])
const router = useRouter()

const fetchInquiries = async () => {
  isLoading.value = true
  loadError.value = null
  try {
    const { data, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/seller/inquiries')).get().json()
    if (error.value) {
      loadError.value = (error.value.data?.message || error.value.message || 'No se pudieron cargar las conversaciones') as string
      return
    }
    items.value = Array.isArray(data.value?.data) ? data.value.data : []
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchInquiries)

const hasItems = computed(() => items.value.length > 0)

const goToProduct = (slug: string, roomKey: string) => {
  router.push({ name: 'store-slug', params: { slug }, query: { open_chat: '1', room_key: roomKey } })
}
</script>

<template>
  <VContainer class="py-6">
    <VRow>
      <VCol cols="12">
        <VCard class="pa-6">
          <VCardTitle>Interacciones de interés (pre-compra)</VCardTitle>
          <VCardText>
            <div v-if="isLoading" class="d-flex justify-center">
              <VProgressCircular color="primary" indeterminate size="32" width="3" />
            </div>
            <VAlert v-else-if="loadError" type="error" variant="tonal">{{ loadError }}</VAlert>
            <div v-else>
              <VTable v-if="hasItems">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th>Room</th>
                    <th>Último mensaje</th>
                    <th style="width: 1%"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="it in items" :key="`${it.product_id}-${it.room_key}`">
                    <td>
                      <strong>{{ it.product_title }}</strong>
                    </td>
                    <td class="text-medium-emphasis">
                      {{ it.room_key }}
                    </td>
                    <td class="text-medium-emphasis">
                      {{ it.last_at }}
                    </td>
                    <td>
                      <VBtn size="small" variant="text" color="primary" @click="goToProduct(it.product_slug, it.room_key)">
                        Ver publicación
                      </VBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <p v-else class="text-medium-emphasis">Aún no hay conversaciones.</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
  
</template>

<style scoped>
</style>
