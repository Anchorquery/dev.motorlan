<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useRouter } from 'vue-router'
import SellerChatModal from './components/SellerChatModal.vue'

interface InquiryItem {
  product_id: number
  product_title: string
  product_slug: string
  room_key: string
  last_at: string
  unread?: number
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

// Table columns
const headers = [
  { title: 'Producto', key: 'product' },
  { title: 'Sala', key: 'room' },
  { title: 'Último mensaje', key: 'last_at' },
  { title: 'No leídos', key: 'unread' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

// Modal state
const activeRoom = ref<{ productId: number; roomKey: string; productTitle: string } | null>(null)
const openReply = (it: InquiryItem) => {
  activeRoom.value = { productId: it.product_id, roomKey: it.room_key, productTitle: it.product_title }
}
const handleMarkedRead = async () => {
  // refresh list to update unread counts
  await fetchInquiries()
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
              <VDataTable
                :headers="headers"
                :items="items"
                class="text-no-wrap"
              >
                <template #item.product="{ item }">
                  <div class="d-flex align-center gap-2">
                    <span class="font-weight-medium">{{ item.product_title }}</span>
                  </div>
                </template>
                <template #item.room="{ item }">
                  <code class="text-medium-emphasis">{{ item.room_key }}</code>
                </template>
                <template #item.unread="{ item }">
                  <VBadge v-if="item.unread && item.unread > 0" :content="item.unread" color="error" inline />
                  <span v-else class="text-disabled">0</span>
                </template>
                <template #item.actions="{ item }">
                  <div class="d-flex align-center">
                    <VBtn size="small" variant="text" color="primary" @click="openReply(item)">Responder</VBtn>
                    <VBtn size="small" variant="text" color="secondary" @click="goToProduct(item.product_slug, item.room_key)">Ver publicación</VBtn>
                  </div>
                </template>
              </VDataTable>
              <p v-if="!hasItems" class="text-medium-emphasis">Aún no hay conversaciones.</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <SellerChatModal
      v-if="activeRoom"
      :product-id="activeRoom.productId"
      :room-key="activeRoom.roomKey"
      :product-title="activeRoom.productTitle"
      @close="activeRoom = null"
      @read="handleMarkedRead"
    />
  </VContainer>
  
</template>

<style scoped>
</style>
