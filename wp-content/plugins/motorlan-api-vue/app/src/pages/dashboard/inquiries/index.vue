<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useRouter } from 'vue-router'
import SellerChatModal from './components/SellerChatModal.vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

// Mapeo de locales de i18n a locales de Intl.DateTimeFormat
const getLocaleCode = (): string => {
  const lang = locale.value || 'es'
  const localeMap: Record<string, string> = {
    es: 'es-ES',
    en: 'en-US',
    eu: 'eu-ES', // Euskera
  }
  return localeMap[lang] || 'es-ES'
}

// Formateador de fecha localizado
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
const router = useRouter()
const searchQuery = ref('')
const itemsPerPage = ref(10)
const page = ref(1)

const fetchInquiries = async () => {
  isLoading.value = true
  loadError.value = null
  try {
    // Note: If the API supports server-side filtering/pagination, we should implement it here.
    // For now keeping client-side fetch but styling it to match others.
    const { data, error } = await useApi<InquiryItem[]>(createUrl('/wp-json/motorlan/v1/seller/inquiries')).get().json()
    if (error.value) {
      loadError.value = (error.value.data?.message || error.value.message || t('inquiries.load_error')) as string
      return
    }
    items.value = Array.isArray(data.value?.data) ? data.value.data : []
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchInquiries)

const goToProduct = (slug: string) => {
  // URL absoluta para navegación cross-base (desde mi-cuenta a la tienda)
  window.open(`/marketplace-motorlan/${slug}`, '_blank')
}

// Table columns
const headers = [
  { title: t('inquiries.user'), key: 'user' },
  { title: t('inquiries.publication'), key: 'product' },
  { title: t('inquiries.date') || 'Fecha', key: 'last_at' },
  { title: t('inquiries.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const sortBy = ref([{ key: 'last_at', order: 'desc' as const }])

// Modal state
const activeRoom = ref<{ productId: number; roomKey: string; productTitle: string; productImage: string | null } | null>(null)
const openReply = (it: InquiryItem) => {
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

// Helper to get image URL
const getImageUrl = (image: string | { url?: string; src?: string; sizes?: Record<string, string | { url?: string }> } | null | undefined, size = 'thumbnail'): string => {
  if (!image) return ''
  if (typeof image === 'string') return image
  
  let imageObj: { url?: string; src?: string; sizes?: Record<string, string | { url?: string }> } = image
  if (Array.isArray(image) && image.length > 0) {
    imageObj = image[0]
  }
  
  if (imageObj.sizes) {
    const sizeData = imageObj.sizes[size]
    if (typeof sizeData === 'string') return sizeData
    if (sizeData && typeof sizeData === 'object' && 'url' in sizeData) return sizeData.url || ''
  }
  
  return imageObj.url || imageObj.src || ''
}

const getInitials = (value: string): string => {
  if (!value) return 'U'
  const parts = value.split(' ').filter(Boolean)
  // Tomar solo la primera letra de cada palabra (máximo 2 palabras)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

// Client-side filtering
const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value
  const q = searchQuery.value.toLowerCase()
  return items.value.filter(item => 
    (item.user_name || '').toLowerCase().includes(q) ||
    (item.product_title || '').toLowerCase().includes(q)
  )
})

const refresh = () => {
  fetchInquiries()
}
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between flex-wrap gap-4">
        <span class="text-h5 text-premium-title">{{ t('inquiries.title') || 'Interesados' }}</span>
      </VCardTitle>

      <VCardText class="pa-6 pb-0">
        <VRow>
           <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="searchQuery"
              :placeholder="t('inquiries.search_placeholder') || 'Buscar...'"
              prepend-inner-icon="tabler-search"
              clearable
              class="elevation-0"
            />
          </VCol>

          <VCol
            cols="12"
            md="2"
          >
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
        >
        <!-- Last Message Date Column -->
        <template #item.last_at="{ item }: { item: InquiryItem }">
          <div class="d-flex flex-column py-3">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ formatLocalizedDate(item.last_at) }}
            </span>
            <VTooltip
              activator="parent"
              location="top"
            >
              {{ new Date(item.last_at).toLocaleString(getLocaleCode()) }}
            </VTooltip>
          </div>
        </template>
        <!-- User Column -->
        <template #item.user="{ item }: { item: InquiryItem }">
          <div class="d-flex align-center gap-3 py-3">
            <VAvatar size="44" variant="tonal" color="primary" class="border">
              <VImg v-if="item.user_avatar" :src="item.user_avatar" cover />
              <span v-else class="font-weight-bold">{{ getInitials(item.user_name || 'Usuario') }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-high-emphasis text-body-1">{{ item.user_name || 'Usuario desconocido' }}</span>
              <span class="text-caption text-medium-emphasis">Comprador interesado</span>
            </div>
          </div>
        </template>

        <!-- Product Column -->
        <template #item.product="{ item }: { item: InquiryItem }">
          <div class="d-flex align-center gap-3 py-3" style="max-width: 350px;">
            <VAvatar
              rounded
              variant="tonal"
              size="48"
              class="border flex-shrink-0"
            >
              <VImg :src="getImageUrl(item.product_image)" cover />
            </VAvatar>
            <div class="d-flex flex-column overflow-hidden">
              <span 
                class="font-weight-medium text-high-emphasis text-body-1 text-truncate"
                style="max-width: 260px;"
                :title="item.product_title"
              >
                {{ item.product_title }}
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
              class="rounded-pill"
              @click="openReply(item)"
            >
              <VIcon icon="tabler-message-circle" start />
              Responder
            </VBtn>
            
            <IconBtn
              size="small"
              color="secondary"
              variant="tonal"
              @click="goToProduct(item.product_slug)"
            >
              <VIcon icon="tabler-external-link" size="18" />
              <VTooltip activator="parent" location="top">Ver publicación</VTooltip>
            </IconBtn>
          </div>
        </template>
        
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

    <SellerChatModal
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
