<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useI18n } from 'vue-i18n'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import BuyerChatModal from './components/BuyerChatModal.vue'

const { t, locale } = useI18n()
const { formatMotorName } = useMotorFormatter()

// Interface matching the expected API response
interface InquiryItem {
  product_id: number
  product_title: string
  product_slug: string
  product_image?: string | { url?: string; src?: string; sizes?: Record<string, string | { url?: string }> } | null
  room_key: string
  last_at: string
  unread?: number
  user_name?: string
  user_avatar?: string
}

const isLoading = ref(false)
const loadError = ref<string | null>(null)
const items = ref<InquiryItem[]>([])
const searchQuery = ref('')
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref([{ key: 'last_at', order: 'desc' as const }])

// Localized date formatting
const getLocaleCode = (): string => {
  const lang = locale.value || 'es'
  const localeMap: Record<string, string> = {
    es: 'es-ES',
    en: 'en-US',
    eu: 'eu-ES',
  }
  return localeMap[lang] || 'es-ES'
}

const formatLocalizedDate = (value: string): string => {
  if (!value) return ''
  const date = new Date(value)
  const now = new Date()
  const isToday = date.toDateString() === now.toDateString()
  
  if (isToday) {
    return new Intl.DateTimeFormat(getLocaleCode(), { 
      hour: '2-digit', 
      minute: '2-digit' 
    }).format(date)
  }
  
  return new Intl.DateTimeFormat(getLocaleCode(), { 
    month: 'short', 
    day: 'numeric' 
  }).format(date)
}

const fetchInquiries = async () => {
  isLoading.value = true
  loadError.value = null
  try {
    const { data, error } = await useApi<any>(createUrl('/wp-json/motorlan/v1/buyer/inquiries')).get().json()
    if (error.value) {
      loadError.value = 'No se pudieron cargar las consultas'
      return
    }
    items.value = Array.isArray(data.value?.data) ? data.value.data : []
  } finally {
    isLoading.value = false
  }
}

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value
  const q = searchQuery.value.toLowerCase()
  return items.value.filter(item => 
    (item.user_name || '').toLowerCase().includes(q) ||
    (item.product_title || '').toLowerCase().includes(q)
  )
})

// Chat modal state
const activeRoom = ref<{ productId: number; roomKey: string; productTitle: string; productImage: string | null } | null>(null)

const openChat = (it: InquiryItem) => {
  activeRoom.value = { 
    productId: it.product_id, 
    roomKey: it.room_key, 
    productTitle: it.product_title,
    productImage: getImageUrl(it.product_image) || null
  }
}

const handleMarkedRead = async () => {
  await fetchInquiries()
}

// Helpers
const getImageUrl = (image: any, size = 'thumbnail'): string => {
  if (!image) return ''
  if (typeof image === 'string') return image
  
  let imageObj = image
  if (Array.isArray(image) && image.length > 0) imageObj = image[0]
  
  if (imageObj?.sizes) {
    const sizeData = imageObj.sizes[size]
    if (typeof sizeData === 'string') return sizeData
    if (sizeData?.url) return sizeData.url
  }
  
  return imageObj?.url || imageObj?.src || ''
}

const getInitials = (value: string): string => {
  if (!value) return 'U'
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

const goToProduct = (item: InquiryItem) => {
  window.open(`/marketplace-motorlan/${item.product_slug}/`, '_blank')
}

onMounted(fetchInquiries)

const headers = [
  { title: t('inquiries.seller') || 'Vendedor', key: 'user' },
  { title: t('inquiries.publication') || 'Publicación', key: 'product' },
  { title: t('inquiries.date') || 'Fecha', key: 'last_at' },
  { title: t('inquiries.actions') || 'Acciones', key: 'actions', sortable: false, align: 'end' as const },
]
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between flex-wrap gap-4">
        <span class="text-h5 text-premium-title">Consultas Enviadas</span>
      </VCardTitle>

      <VCardText class="pa-6 pb-0">
        <VRow>
          <VCol cols="12" md="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por vendedor o motor..."
              prepend-inner-icon="tabler-search"
              clearable
              class="elevation-0"
            />
          </VCol>

          <VCol cols="12" md="2">
            <AppSelect
              v-model="itemsPerPage"
              :items="[5, 10, 20, 25, 50]"
              placeholder="Mostrar"
              prepend-inner-icon="tabler-list-numbers"
            />
          </VCol>
        </VRow>
      </VCardText>

      <div class="px-6 pb-6">
        <VDataTable
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="filteredItems"
          :loading="isLoading"
          v-model:sort-by="sortBy"
          class="text-no-wrap"
          hover
          :no-data-text="t('inquiries.no_data_text_buyer') || 'No has realizado ninguna consulta todava.'"
        >
          <!-- Date Column -->
          <template #item.last_at="{ item }: { item: InquiryItem }">
            <div class="d-flex flex-column py-3">
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ formatLocalizedDate(item.last_at) }}
              </span>
              <VTooltip activator="parent" location="top">
                {{ new Date(item.last_at).toLocaleString(getLocaleCode()) }}
              </VTooltip>
            </div>
          </template>

          <!-- Seller Column -->
          <template #item.user="{ item }: { item: InquiryItem }">
            <div class="d-flex align-center gap-3 py-3">
              <VAvatar size="44" variant="tonal" color="primary" class="border">
                <VImg v-if="item.user_avatar" :src="item.user_avatar" cover />
                <span v-else class="font-weight-bold">{{ getInitials(item.user_name || 'Vendedor') }}</span>
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="font-weight-medium text-high-emphasis text-body-1">{{ item.user_name || 'Vendedor' }}</span>
                <span class="text-caption text-medium-emphasis">{{ t('inquiries.seller_label') || 'Vendedor del equipo' }}</span>
              </div>
            </div>
          </template>

          <!-- Product Column -->
          <template #item.product="{ item }: { item: InquiryItem }">
            <div class="d-flex align-center gap-3 py-3" style="max-width: 350px;">
              <VAvatar rounded variant="tonal" size="48" class="border flex-shrink-0">
                <VImg :src="getImageUrl(item.product_image)" cover />
              </VAvatar>
              <div class="d-flex flex-column overflow-hidden">
                <span 
                  class="font-weight-medium text-high-emphasis text-body-1 text-truncate"
                  style="max-width: 260px;"
                >
                  {{ formatMotorName({ title: item.product_title, ...item }) || item.product_title }}
                  <VTooltip activator="parent" location="top">{{ formatMotorName({ title: item.product_title, ...item }) || item.product_title }}</VTooltip>
                </span>
                <span class="text-caption text-medium-emphasis">ID: {{ item.product_id }}</span>
              </div>
            </div>
          </template>

          <!-- Actions Column -->
          <template #item.actions="{ item }: { item: InquiryItem }">
            <div class="d-flex justify-end gap-2">
              <VBtn
                size="small"
                color="primary"
                variant="tonal"
                class="rounded-pill position-relative"
                @click="openChat(item)"
              >
                <VIcon icon="tabler-message-circle" start />
                {{ t('inquiries.view_chat') || 'Ver Chat' }}
                
                <!-- Unread count -->
                <VBadge
                  v-if="item.unread && item.unread > 0"
                  :content="item.unread"
                  color="error"
                  offset-x="-10"
                  offset-y="-10"
                  class="unread-badge-absolute"
                />
              </VBtn>
              
              <IconBtn
                size="small"
                color="secondary"
                variant="tonal"
                @click="goToProduct(item)"
              >
                <VIcon icon="tabler-external-link" size="18" />
                <VTooltip activator="parent" location="top">{{ t('inquiries.go_to_publication') || 'Ir a la publicación' }}</VTooltip>
              </IconBtn>
            </div>
          </template>

          <!-- Pagination -->
          <template #bottom>
            <TablePagination
              v-model:page="page"
              :items-per-page="itemsPerPage"
              :total-items="filteredItems.length"
              class="mt-4"
            />
          </template>
        </VDataTable>
      </div>
    </VCard>

    <BuyerChatModal
      v-if="activeRoom"
      :product-id="activeRoom.productId"
      :room-key="activeRoom.roomKey"
      :product-title="activeRoom.productTitle"
      :product-image="activeRoom.productImage"
      @close="activeRoom = null"
      @read="handleMarkedRead"
    />
  </div>
</template>

<style scoped lang="scss">
.unread-badge-absolute {
  position: absolute;
  top: 0;
  right: 0;
  transform: translate(25%, -25%);
  z-index: 1;
}
</style>
