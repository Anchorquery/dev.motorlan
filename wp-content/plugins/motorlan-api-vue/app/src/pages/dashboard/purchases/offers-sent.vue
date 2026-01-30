<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()

const headers = [
  { title: 'Publicacion', key: 'publication_title' },
  { title: 'Monto Ofertado', key: 'offer_amount' },
  { title: 'Fecha', key: 'offer_date' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'actions', sortable: false },
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

const statusOptions = [
  { title: 'Todos', value: '' },
  { title: 'Pendientes', value: 'pending' },
  { title: 'Esperando confirmación', value: 'accepted_pending_confirmation' },
  { title: 'Confirmados', value: 'confirmed' },
  { title: 'Rechazados', value: 'rejected' },
  { title: 'Expirados', value: 'expired' },
]

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

  return `/wp-json/motorlan/v1/offers/sent?${params.toString()}`
})

const { data: offersData, execute: fetchOffers, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)
const isConfirmingOffer = ref(false)
const router = useRouter()

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
})

const offers = computed(() => (offersData.value?.data || offersData.value || []).filter(Boolean))
const totalOffers = computed(() => (offersData.value?.pagination?.total) || 0)

const formatCurrency = (value: number | string) => {
  const numericValue = Number(value)
  if (Number.isNaN(numericValue))
    return value

  return new Intl.NumberFormat('es-VE', {
    style: 'currency',
    currency: 'EUR',
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

const openOfferDetails = (offer: any) => {
  selectedOffer.value = offer
  isDetailDialogOpen.value = true
}

const confirmOffer = async (offerId: number) => {
  if (isConfirmingOffer.value)
    return

  isConfirmingOffer.value = true
  try {
    const { data: response, error } = await useApi(`/wp-json/motorlan/v1/offers/${offerId}/confirm`).post().json()

    if (error.value)
      throw error.value

    const payload = response.value || null
    const updatedOffer = payload?.data || null

    if (selectedOffer.value?.id === offerId && updatedOffer)
      selectedOffer.value = updatedOffer

    await fetchOffers()
    isDetailDialogOpen.value = false

    if (payload?.purchase_uuid)
      router.push(`/dashboard/purchases/${payload.purchase_uuid}`)

  }
  catch (error) {
    console.error(error)
  }
  finally {
    isConfirmingOffer.value = false
    if (selectedOffer.value?.id === offerId && isDetailDialogOpen.value === false)
      selectedOffer.value = null
  }
}

const withdrawOffer = async (offerId: number) => {
  try {
    await useApi(`/wp-json/motorlan/v1/offers/${offerId}`).delete().execute()
    isDetailDialogOpen.value = false
    selectedOffer.value = null
    await fetchOffers()
  }
  catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <VCard
    id="offer-sent-list"
    class="motor-card-enhanced"
  >
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">{{ t('Ofertas Enviadas') }}</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <VRow class="gy-4">
        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar oferta..."
            clearable
            prepend-inner-icon="tabler-search"
          />
        </VCol>

        <VCol
          cols="12"
          sm="3"
        >
          <AppSelect
            v-model="statusFilter"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            placeholder="Filtrar por estado"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>

        <VCol
          cols="12"
          sm="5"
        >
          <AppDateTimePicker
            v-model="dateRange"
            placeholder="Rango de fechas"
            clearable
            :config="{ mode: 'range', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="offers"
      :items-length="totalOffers"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap pb-4"
      @update:options="updateOptions"
    >
      <template #item.offer_amount="{ item }">
        <span class="text-body-1 font-weight-medium text-premium-price">{{ formatCurrency(item.offer_amount) }}</span>
      </template>

      <template #item.offer_date="{ item }">
        <span class="text-body-2 text-high-emphasis">{{ new Date(item.offer_date).toLocaleDateString() }}</span>
      </template>

      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus(item.status)"
          density="comfortable"
          label
          size="small"
          class="font-weight-medium"
        >
          {{ resolveStatus(item.status).text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn
            color="primary"
            variant="tonal"
            size="small"
          >
            <VIcon
              icon="tabler-dots-vertical"
              size="18"
            />
            <VMenu activator="parent">
              <VList class="py-0">
                <VListItem
                  value="details"
                  prepend-icon="tabler-eye"
                  @click="openOfferDetails(item)"
                >
                  Ver detalle
                </VListItem>
                <VListItem
                  v-if="item.status === 'accepted_pending_confirmation'"
                  value="confirm"
                  prepend-icon="tabler-check"
                  class="text-success"
                  :disabled="isConfirmingOffer"
                  @click="confirmOffer(item.id)"
                >
                  Confirmar compra
                </VListItem>
                <VListItem
                  v-if="['pending', 'expired'].includes(item.status)"
                  value="withdraw"
                  prepend-icon="tabler-arrow-back-up"
                  class="text-error"
                  @click="withdrawOffer(item.id)"
                >
                  Retirar oferta
                </VListItem>
              </VList>
            </VMenu>
          </IconBtn>
        </div>
      </template>

      <template #bottom>
        <VDivider />
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalOffers"
          class="pa-4"
        />
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDetailDialogOpen"
      max-width="600"
    >
      <VCard v-if="selectedOffer" class="motor-card-enhanced overflow-hidden">
        <VCardTitle class="pa-0">
          <div class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-file-description" color="primary" />
              <span class="text-h6 font-weight-bold text-premium-title">Detalle de la Oferta</span>
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
                <div>
                  <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold letter-spacing-1">Publicación</div>
                  <div class="text-h6 font-weight-bold text-high-emphasis">{{ selectedOffer.publication_title }}</div>
                </div>
              </div>
            </VCol>

            <!-- Monto y Fecha -->
            <VCol cols="12" sm="6">
              <div class="d-flex flex-column gap-1">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-currency-euro" size="18" class="text-primary" />
                  <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Monto Ofertado</span>
                </div>
                <div class="text-h5 font-weight-bold text-premium-price">{{ formatCurrency(selectedOffer.offer_amount) }}</div>
              </div>
            </VCol>

            <VCol cols="12" sm="6">
              <div class="d-flex flex-column gap-1">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-calendar" size="18" class="text-primary" />
                  <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Fecha Envío</span>
                </div>
                <div class="text-body-1 font-weight-medium">{{ new Date(selectedOffer.offer_date).toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' }) }}</div>
              </div>
            </VCol>

            <VCol cols="12">
              <VDivider class="my-2 border-dashed" />
            </VCol>

            <!-- Estado -->
            <VCol cols="12" sm="6">
              <div class="d-flex flex-column gap-2">
                <div class="d-flex align-center gap-2">
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

            <VCol v-if="selectedOffer.status === 'accepted_pending_confirmation'" cols="12" sm="6">
              <div class="d-flex flex-column gap-2 pa-3 bg-light-warning rounded border-warning border-opacity-25">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-hourglass-high" size="18" class="text-warning" />
                  <span class="text-caption text-warning-darken-2 text-uppercase font-weight-bold">Tiempo Límite</span>
                </div>
                <div class="text-body-2 font-weight-bold text-warning-darken-2">
                  Expira en: {{ formatTimeRemaining(selectedOffer.time_to_expire) }}
                </div>
              </div>
            </VCol>

            <!-- Oferta Aceptada el -->
            <VCol v-if="selectedOffer.accepted_at" cols="12">
              <div class="d-flex align-center gap-2 bg-light-success pa-3 rounded">
                <VIcon icon="tabler-circle-check" color="success" />
                <div>
                  <span class="text-caption text-success-darken-2 text-uppercase font-weight-bold d-block">Oferta aceptada el</span>
                  <span class="text-body-2 font-weight-medium">{{ new Date(selectedOffer.accepted_at).toLocaleString() }}</span>
                </div>
              </div>
            </VCol>

            <!-- Comentario -->
            <VCol cols="12">
              <div class="pa-4 bg-light rounded-lg border">
                <div class="d-flex align-center gap-2 mb-2">
                  <VIcon icon="tabler-message-2" size="18" class="text-medium-emphasis" />
                  <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Comentario Adicional</span>
                </div>
                <div v-if="selectedOffer.justification" class="text-body-1 font-italic text-high-emphasis">
                  "{{ selectedOffer.justification }}"
                </div>
                <div v-else class="text-body-2 text-medium-emphasis font-italic">
                  Sin comentarios adicionales para esta oferta.
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
          <VBtn
            v-if="selectedOffer.status === 'accepted_pending_confirmation'"
            color="success"
            variant="flat"
            class="px-6"
            :disabled="isConfirmingOffer"
            @click="confirmOffer(selectedOffer.id)"
          >
            <VIcon icon="tabler-check" start size="18" />
            Confirmar compra
          </VBtn>
          <VBtn
            v-if="['pending', 'expired'].includes(selectedOffer.status)"
            color="error"
            variant="tonal"
            class="px-6"
            @click="withdrawOffer(selectedOffer.id)"
          >
            <VIcon icon="tabler-arrow-back-up" start size="18" />
            Retirar oferta
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
