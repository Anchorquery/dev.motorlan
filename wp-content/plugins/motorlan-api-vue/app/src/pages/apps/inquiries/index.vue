<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useRouter } from 'vue-router'
import SellerChatModal from './components/SellerChatModal.vue'

// Interface matching the expected API response (extended for new requirements)
interface InquiryItem {
  product_id: number
  product_title: string
  product_slug: string
  product_image?: any // Image object or string
  room_key: string
  last_at: string
  unread?: number
  user_name?: string
  user_avatar?: string
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
  { title: 'Usuario', key: 'user' },
  { title: 'Publicación', key: 'product' },
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

// Helper to get image URL (simplified version of the one in publications)
const getImageUrl = (image: any, size = 'thumbnail'): string => {
  if (!image) return ''
  if (typeof image === 'string') return image
  
  // Handle WP image object structure
  let imageObj = image
  if (Array.isArray(image) && image.length > 0) imageObj = image[0]
  
  if (imageObj.sizes) {
    if (Array.isArray(imageObj.sizes)) {
      const match = imageObj.sizes.find((s: any) => s.name === size || s.slug === size)
      if (match) return match.url || match.src
    } else if (typeof imageObj.sizes === 'object') {
      const sizeEntry = imageObj.sizes[size]
      if (sizeEntry) return sizeEntry.url || sizeEntry.src || sizeEntry
    }
  }
  
  return imageObj.url || imageObj.src || ''
}

const getInitials = (value: string): string => {
  if (!value) return 'U'
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.toUpperCase()).join('') || 'U'
}
</script>

<template>
  <VContainer class="py-6">
    <VRow>
      <VCol cols="12">
        <VCard class="pa-6">
          <VCardTitle class="mb-4">Interacciones de interés (pre-compra)</VCardTitle>
          <VCardText class="pa-0">
            <div v-if="isLoading" class="d-flex justify-center py-8">
              <VProgressCircular color="primary" indeterminate size="32" width="3" />
            </div>
            <VAlert v-else-if="loadError" type="error" variant="tonal" class="ma-4">{{ loadError }}</VAlert>
            <div v-else>
              <VDataTable
                :headers="headers"
                :items="items"
                class="text-no-wrap"
                hover
              >
                <!-- User Column -->
                <template #item.user="{ item }">
                  <div class="d-flex align-center gap-3">
                    <VAvatar size="38" variant="tonal" color="primary">
                      <VImg v-if="item.user_avatar" :src="item.user_avatar" />
                      <span v-else>{{ getInitials(item.user_name || 'Usuario') }}</span>
                    </VAvatar>
                    <div class="d-flex flex-column">
                      <span class="font-weight-medium text-high-emphasis">{{ item.user_name || 'Usuario desconocido' }}</span>
                      <span class="text-caption text-medium-emphasis">Interesado</span>
                    </div>
                  </div>
                </template>

                <!-- Product Column -->
                <template #item.product="{ item }">
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      rounded
                      variant="tonal"
                      size="48"
                      :image="getImageUrl(item.product_image)"
                      icon="tabler-photo"
                    />
                    <div class="d-flex flex-column">
                      <span class="font-weight-medium text-high-emphasis text-body-1">{{ item.product_title }}</span>
                      <!-- Optional: Add more details if available, e.g. price or brand -->
                    </div>
                  </div>
                </template>

                <!-- Actions Column -->
                <template #item.actions="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VBtn
                      prepend-icon="tabler-message-circle"
                      size="small"
                      color="primary"
                      @click="openReply(item)"
                    >
                      Responder
                    </VBtn>
                    
                    <VTooltip location="top" text="Ver publicación">
                      <template #activator="{ props }">
                        <IconBtn
                          v-bind="props"
                          size="small"
                          color="secondary"
                          @click="goToProduct(item.product_slug, item.room_key)"
                        >
                          <VIcon icon="tabler-external-link" />
                        </IconBtn>
                      </template>
                    </VTooltip>
                  </div>
                </template>
                
                <template #no-data>
                  <div class="d-flex flex-column align-center justify-center py-8 text-medium-emphasis">
                    <VIcon icon="tabler-message-off" size="48" class="mb-4 text-disabled" />
                    <p>Aún no hay conversaciones.</p>
                  </div>
                </template>
              </VDataTable>
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
/* Optional: Add specific styles if needed, but Vuetify classes should handle most */
</style>
