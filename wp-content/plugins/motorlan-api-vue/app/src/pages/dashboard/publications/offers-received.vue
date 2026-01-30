<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import type { ImagenDestacada } from '@/interfaces/publicacion'

const { t } = useI18n()

const headers = [
  { title: 'Publicacion', value: 'publication_title' },
  { title: 'Ofertante', value: 'user_name' },
  { title: 'Monto', value: 'offer_amount' },
  { title: 'Fecha', value: 'offer_date' },
  { title: 'Estado', value: 'status' },
  { title: 'Acciones', value: 'actions', sortable: false },
]

const searchQuery = ref('')
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()
const statusFilter = ref('')
const dateRange = ref('')
const isDetailDialogOpen = ref(false)
const selectedOffer = ref<any | null>(null)
const router = useRouter()
const route = useRoute()

const snackbarState = ref({
  show: false,
  message: '',
  type: 'success' as 'success' | 'error',
})

const showSnackbar = (message: string, type: 'success' | 'error' = 'success') => {
  snackbarState.value = {
    show: true,
    message,
    type,
  }
}

const statusOptions = [
  { title: 'Todos', value: '' },
  { title: 'Pendientes', value: 'pending' },
  { title: 'Esperando confirmación', value: 'accepted_pending_confirmation' },
  { title: 'Confirmados', value: 'confirmed' },
  { title: 'Rechazados', value: 'rejected' },
  { title: 'Expirados', value: 'expired' },
]

const queryOfferId = computed(() => {
  const raw = route.query.offer_id
  if (!raw)
    return null

  const value = Array.isArray(raw) ? raw[0] : raw
  const parsed = Number(value)
  return Number.isNaN(parsed) ? null : parsed
})

const openedQueryOfferId = ref<number | null>(null)

const dateRangeParams = computed(() => {
  if (!dateRange.value)
    return { from: '', to: '' }

  const [from, to] = dateRange.value.split(' to ')

  return {
    from: from || '',
    to: (to || from) || '',
  }
})

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (statusFilter.value)
    params.append('status', statusFilter.value)
  if (dateRangeParams.value.from)
    params.append('date_from', dateRangeParams.value.from)
  if (dateRangeParams.value.to)
    params.append('date_to', dateRangeParams.value.to)
  if (page.value)
    params.append('page', page.value.toString())
  if (itemsPerPage.value)
    params.append('per_page', itemsPerPage.value.toString())
  if (sortBy.value)
    params.append('orderby', sortBy.value)
  if (orderBy.value)
    params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/offers/received?${params.toString()}`
})

const { data: offersData, execute: fetchOffers, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)
const updatingOfferId = ref<number | null>(null)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchOffers()
  isSearching.value = false
}, 300)

watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

watch(
  [searchQuery, statusFilter, dateRange],
  () => {
    if (page.value !== 1)
      page.value = 1
    else
      debouncedFetch()
  },
  { deep: true },
)

onMounted(() => {
  fetchOffers()
  void fetchBrands()
})

const offers = computed(() => (offersData.value?.data || offersData.value || []).filter(Boolean))
const totalOffers = computed(() => (offersData.value?.pagination?.total) || 0)

watch(
  [offers, queryOfferId],
  ([currentOffers, offerId]) => {
    if (!offerId || openedQueryOfferId.value === offerId)
      return

    const match = currentOffers?.find((offer: any) => offer?.id === offerId)
    if (!match)
      return

    openedQueryOfferId.value = offerId
    selectedOffer.value = match
    isDetailDialogOpen.value = true

    const newQuery = { ...route.query }
    delete newQuery.offer_id
    router.replace({ query: newQuery }).catch(() => {})
  },
  { immediate: true }
)

type BrandTerm = { term_id: number; name: string; slug: string }
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
const brands = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [Number(b.term_id), b])))
const brandBySlug = computed<Record<string, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [String(b.slug), b])))

const replaceOfferInTable = (updatedOffer: any) => {
  if (!updatedOffer || !offersData.value)
    return

  const current = offersData.value

  if (Array.isArray(current?.data)) {
    const index = current.data.findIndex((item: any) => item?.id === updatedOffer.id)
    if (index !== -1) {
      const newData = [...current.data]
      newData[index] = { ...newData[index], ...updatedOffer }
      offersData.value = { ...current, data: newData }
    }
    return
  }

  if (Array.isArray(current)) {
    const index = current.findIndex((item: any) => item?.id === updatedOffer.id)
    if (index !== -1) {
      const newData = [...current]
      newData[index] = { ...newData[index], ...updatedOffer }
      offersData.value = newData
    }
  }
}

const updateOfferStatus = async (offerId: number, status: 'accepted' | 'rejected') => {
  if (updatingOfferId.value === offerId)
    return

  updatingOfferId.value = offerId
  try {
    const { data: response, error } = await useApi(`/wp-json/motorlan/v1/offers/${offerId}/status`).post({ status }).json()

    if (error.value)
      throw error.value

    const payload = response.value || null
    const updatedOffer = payload?.data || payload

    replaceOfferInTable(updatedOffer)

    if (selectedOffer.value?.id === offerId && updatedOffer)
      selectedOffer.value = { ...selectedOffer.value, ...updatedOffer }

    await fetchOffers()

    if (selectedOffer.value?.id === offerId) {
      const stillVisible = offers.value.some((offer: any) => offer?.id === offerId)
      if (!stillVisible) {
        selectedOffer.value = null
        isDetailDialogOpen.value = false
      }
    }

    const successMessage = payload?.message || (status === 'accepted' ? 'Oferta aceptada' : 'Oferta rechazada')
    showSnackbar(successMessage, 'success')
  }
  catch (error) {
    console.error(error)
    const err = error as any
    const errorMessage = err?.data?.message || err?.message || 'No fue posible actualizar la oferta.'
    showSnackbar(errorMessage, 'error')
  }
  finally {
    updatingOfferId.value = null
  }
}

const openOfferDetails = (offer: any) => {
  selectedOffer.value = offer
  isDetailDialogOpen.value = true
}

const formatCurrency = (value: number | string) => {
  const numericValue = Number(value)
  if (Number.isNaN(numericValue))
    return value

  return new Intl.NumberFormat('es-VE', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numericValue)
}

const formatTimeRemaining = (seconds?: number | null) => {
  if (seconds === null || seconds === undefined)
    return '—'

  if (seconds <= 0)
    return 'Expirada'

  const totalMinutes = Math.ceil(seconds / 60)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  if (hours > 0 && minutes > 0)
    return `${hours} h ${minutes} min`
  if (hours > 0)
    return `${hours} h`

  return `${minutes} min`
}

const resolveBrandName = (value: any): string | null => {
  if (!value && value !== 0)
    return null
  if (typeof value === 'object') {
    const name = (value?.name || value?.label || value?.title) as string | undefined
    if (name && name.trim().length)
      return name
    const id = Number(value?.id ?? value?.term_id ?? 0) || null
    if (id && brandById.value[id])
      return brandById.value[id].name
  }
  const asNum = Number(value)
  if (Number.isFinite(asNum) && asNum > 0 && brandById.value[asNum])
    return brandById.value[asNum].name
  const asStr = String(value)
  if (brandBySlug.value[asStr])
    return brandBySlug.value[asStr].name
  return asStr && asStr !== 'null' && asStr !== 'undefined' ? asStr : null
}

const getImageBySize = (image: ImagenDestacada | null | any[], size = 'thumbnail'): string => {
  let imageObj: ImagenDestacada | null = null
  if (Array.isArray(image) && image.length > 0)
    imageObj = image[0] as ImagenDestacada
  else if (image && !Array.isArray(image))
    imageObj = image as ImagenDestacada
  if (!imageObj)
    return ''
  if ((imageObj as any).sizes && (imageObj as any).sizes[size])
    return (imageObj as any).sizes[size] as string
  return (imageObj as any).url || ''
}

const formatPublicationTitle = (pub: any, fallbackTitle?: string): string => {
  if (!pub)
    return fallbackTitle || ''
  const acf = pub.acf || {}
  const parts = [
    pub.title || fallbackTitle,
    resolveBrandName(acf.marca),
    acf.velocidad ? `${acf.velocidad} rpm` : null,
    acf.potencia ? `${acf.potencia} kW` : null,
  ].filter(Boolean) as string[]
  return parts.join(' · ')
}

const getPublicationEntity = (item: any) => (item?.publicacion || item?.motor || null)
const getPublicationSlug = (item: any) =>
  getPublicationEntity(item)?.slug || item?.publication_slug || item?.motor_slug || ''

const resolveStatus = (status: string) => {
  if (status === 'accepted_pending_confirmation')
    return { text: 'Esperando confirmación', color: 'info' }
  if (status === 'confirmed')
    return { text: 'Confirmada', color: 'success' }
  if (status === 'expired')
    return { text: 'Expirada', color: 'warning' }
  if (status === 'rejected')
    return { text: 'Rechazada', color: 'error' }

  return { text: 'Pendiente', color: 'warning' }
}
</script>

<template>
  <VCard class="motor-card-enhanced overflow-visible">
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">{{ t('Ofertas Recibidas') }}</span>
    </VCardTitle>
    <VCardText>
      <VRow>
        <!-- Search -->
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar oferta..."
            prepend-inner-icon="tabler-search"
            class="elevation-0"
          />
        </VCol>

        <!-- Status Filter -->
        <VCol
          cols="12"
          md="3"
        >
          <AppSelect
            v-model="statusFilter"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            placeholder="Estado"
            clearable
            clear-icon="tabler-x"
            prepend-inner-icon="tabler-filter"
          />
        </VCol>

        <!-- Date Range -->
        <VCol
          cols="12"
          md="3"
        >
          <AppDateTimePicker
            v-model="dateRange"
            placeholder="Rango de fechas"
            clearable
            prepend-inner-icon="tabler-calendar"
            :config="{ mode: 'range', dateFormat: 'Y-m-d' }"
          />
        </VCol>

        <!-- Per Page -->
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

    <VDivider class="mt-4" />

    <div class="px-6 pb-6">
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="offers"
        :items-length="totalOffers"
        :loading="isTableLoading || isSearching"
        class="text-no-wrap"
        item-value="id"
        @update:options="updateOptions"
      >
        <template #item.publication_title="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              v-if="getPublicationEntity(item)?.imagen_destacada"
              size="48"
              variant="tonal"
              rounded
              class="border"
              :image="getImageBySize(getPublicationEntity(item)?.imagen_destacada, 'thumbnail')"
            />
            <div class="d-flex flex-column">
              <RouterLink
                v-if="getPublicationSlug(item)"
                :to="`/${getPublicationSlug(item)}`"
                class="text-high-emphasis font-weight-medium text-body-1 text-decoration-none"
              >
                {{ formatPublicationTitle(getPublicationEntity(item), item.publication_title) }}
              </RouterLink>
              <span
                v-else
                class="text-high-emphasis font-weight-medium text-body-1"
              >
                {{ formatPublicationTitle(getPublicationEntity(item), item.publication_title) }}
              </span>
              <span
                v-if="getPublicationEntity(item)?.acf?.tipo_o_referencia"
                class="text-caption text-medium-emphasis"
              >
                Ref: {{ getPublicationEntity(item).acf.tipo_o_referencia }}
              </span>
            </div>
          </div>
        </template>
        <template #item.offer_amount="{ item }">
          <span class="font-weight-bold text-high-emphasis">{{ formatCurrency(item.offer_amount) }}</span>
        </template>
  
        <template #item.offer_date="{ item }">
          <span class="text-medium-emphasis">{{ new Date(item.offer_date).toLocaleDateString() }}</span>
        </template>
  
        <template #item.status="{ item }">
          <VChip
            v-bind="resolveStatus(item.status)"
            density="default"
            label
            size="small"
            class="font-weight-medium"
          />
        </template>
  
        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <IconBtn size="small">
              <VIcon icon="tabler-dots-vertical" />
              <VMenu activator="parent">
                <VList density="compact">
                  <VListItem
                    value="details"
                    prepend-icon="tabler-eye"
                    @click="openOfferDetails(item)"
                  >
                    Ver detalle
                  </VListItem>
                  <VListItem
                    value="accept"
                    prepend-icon="tabler-check"
                    class="text-success"
                    :disabled="!item.can_accept || updatingOfferId === item.id"
                    :title="!item.can_accept ? item.accept_disabled_reason || 'No disponible para aceptar' : undefined"
                    @click="updateOfferStatus(item.id, 'accepted')"
                  >
                    Aceptar
                  </VListItem>
                  <VListItem
                    value="reject"
                    prepend-icon="tabler-x"
                    class="text-error"
                    :disabled="item.status === 'confirmed' || updatingOfferId === item.id"
                    @click="updateOfferStatus(item.id, 'rejected')"
                  >
                    Rechazar
                  </VListItem>
                </VList>
              </VMenu>
            </IconBtn>
          </div>
        </template>
  
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalOffers"
            class="mt-4"
          />
        </template>
      </VDataTableServer>
    </div>

    <div class="px-6 pb-6">
      <VDialog
        v-model="isDetailDialogOpen"
        max-width="600"
      >
        <VCard v-if="selectedOffer" class="motor-card-enhanced overflow-hidden">
          <VCardTitle class="pa-0">
            <div class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-report-money" color="primary" />
                <span class="text-h6 font-weight-bold text-premium-title">Detalle de la Oferta Recibida</span>
              </div>
              <IconBtn @click="isDetailDialogOpen = false">
                <VIcon icon="tabler-x" size="20" />
              </IconBtn>
            </div>
          </VCardTitle>

          <VCardText class="pa-6">
            <VRow>
              <!-- Publicación -->
              <VCol cols="12">
                <div class="d-flex align-center gap-3 mb-4 pa-4 bg-light-primary rounded-lg border-primary border-opacity-10">
                  <VAvatar size="48" color="primary" variant="tonal" rounded>
                    <VIcon icon="tabler-engine" size="24" />
                  </VAvatar>
                  <div class="flex-grow-1">
                    <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold letter-spacing-1">Publicación</div>
                    <div class="text-h6 font-weight-bold text-high-emphasis line-height-1.2">{{ selectedOffer.publication_title }}</div>
                  </div>
                </div>
              </VCol>

              <!-- Ofertante y Monto -->
              <VCol cols="12" sm="6">
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-user" size="18" class="text-primary" />
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Ofertante</span>
                  </div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <VAvatar size="28" color="primary" variant="tonal" class="text-caption font-weight-bold">
                      {{ selectedOffer.user_name?.charAt(0).toUpperCase() }}
                    </VAvatar>
                    <span class="text-body-1 font-weight-medium text-high-emphasis">{{ selectedOffer.user_name }}</span>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" sm="6" class="text-sm-right">
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex align-center gap-2 justify-sm-end">
                    <VIcon icon="tabler-currency-dollar" size="18" class="text-primary" />
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Monto Ofertado</span>
                  </div>
                  <div class="text-h5 font-weight-bold text-premium-price mt-1">{{ formatCurrency(selectedOffer.offer_amount) }}</div>
                </div>
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2 border-dashed" />
              </VCol>

              <!-- Fecha y Estado -->
              <VCol cols="12" sm="6">
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-calendar" size="18" class="text-primary" />
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Fecha Recibida</span>
                  </div>
                  <div class="text-body-1 font-weight-medium text-high-emphasis">
                    {{ new Date(selectedOffer.offer_date).toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' }) }}
                  </div>
                </div>
              </VCol>

              <VCol cols="12" sm="6" class="text-sm-right">
                <div class="d-flex flex-column gap-2">
                  <div class="d-flex align-center gap-2 justify-sm-end">
                    <VIcon icon="tabler-info-circle" size="18" class="text-primary" />
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Estado Actual</span>
                  </div>
                  <div>
                    <VChip
                      v-bind="resolveStatus(selectedOffer.status)"
                      density="comfortable"
                      label
                      class="font-weight-bold"
                    >
                      {{ resolveStatus(selectedOffer.status).text }}
                    </VChip>
                  </div>
                </div>
              </VCol>

              <!-- Tiempo restante (si aplica) -->
              <VCol v-if="selectedOffer.status === 'accepted_pending_confirmation'" cols="12">
                <div class="pa-3 bg-light-warning rounded-lg border-warning border-opacity-25 d-flex align-center gap-3 mt-2">
                  <VAvatar size="32" color="warning" variant="tonal">
                    <VIcon icon="tabler-clock-play" size="18" />
                  </VAvatar>
                  <div>
                    <span class="text-caption text-warning-darken-2 text-uppercase font-weight-bold d-block">Tiempo para confirmar</span>
                    <span class="text-body-2 font-weight-bold text-warning-darken-2">{{ formatTimeRemaining(selectedOffer.time_to_expire) }}</span>
                  </div>
                </div>
              </VCol>

              <!-- Motivo de restricción (si aplica) -->
              <VCol v-if="!selectedOffer.can_accept && ['pending', 'expired'].includes(selectedOffer.status)" cols="12">
                <div class="pa-3 bg-light-error rounded-lg border-error border-opacity-25 d-flex align-center gap-3 mt-2">
                  <VAvatar size="32" color="error" variant="tonal">
                    <VIcon icon="tabler-alert-triangle" size="18" />
                  </VAvatar>
                  <div>
                    <span class="text-caption text-error text-uppercase font-weight-bold d-block">No se puede aceptar</span>
                    <span class="text-body-2 font-weight-medium text-error">{{ selectedOffer.accept_disabled_reason || 'Sin disponibilidad de stock o publicación inactiva.' }}</span>
                  </div>
                </div>
              </VCol>

              <!-- Comentario -->
              <VCol cols="12">
                <div class="pa-4 bg-light rounded-lg border mt-2">
                  <div class="d-flex align-center gap-2 mb-2">
                    <VIcon icon="tabler-message-2" size="18" class="text-medium-emphasis" />
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Comentario del Ofertante</span>
                  </div>
                  <div v-if="selectedOffer.justification" class="text-body-1 font-italic text-high-emphasis">
                    "{{ selectedOffer.justification }}"
                  </div>
                  <div v-else class="text-body-2 text-medium-emphasis font-italic">
                    El ofertante no incluyó comentarios adicionales.
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-4">
            <VSpacer />
            <VBtn
              variant="tonal"
              color="secondary"
              class="px-6"
              @click="isDetailDialogOpen = false"
            >
              Cerrar
            </VBtn>
            
            <template v-if="selectedOffer.can_accept && selectedOffer.status === 'pending'">
              <VBtn
                color="error"
                variant="tonal"
                class="px-6"
                @click="updateOfferStatus(selectedOffer.id, 'rejected')"
              >
                Rechazar
              </VBtn>
              <VBtn
                color="success"
                variant="flat"
                class="px-6"
                @click="updateOfferStatus(selectedOffer.id, 'accepted')"
              >
                Aceptar Oferta
              </VBtn>
            </template>
          </VCardActions>
        </VCard>
      </VDialog>
    </div>

    <VSnackbar
      v-model="snackbarState.show"
      :color="snackbarState.type"
      location="top right"
      elevation="6"
    >
      {{ snackbarState.message }}
    </VSnackbar>
  </VCard>
</template>
