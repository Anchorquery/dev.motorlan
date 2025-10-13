<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()

const headers = [
  { title: 'Publicacion', key: 'publication_title' },
  { title: 'Ofertante', key: 'user_name' },
  { title: 'Monto', key: 'offer_amount' },
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
})

const offers = computed(() => (offersData.value?.data || offersData.value || []).filter(Boolean))
const totalOffers = computed(() => (offersData.value?.pagination?.total) || 0)

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
  }
  catch (error) {
    console.error(error)
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
  <VCard :title="t('Ofertas Recibidas')">
    <VCardText>
      <VRow class="gy-4">
        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar oferta..."
            clearable
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
          />
        </VCol>

        <VCol
          cols="12"
          sm="2"
          class="ms-sm-auto"
        >
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
            label="Por pagina"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider class="mt-4" />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="offers"
      :items-length="totalOffers"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.offer_amount="{ item }">
        <span>{{ formatCurrency(item.offer_amount) }}</span>
      </template>

      <template #item.offer_date="{ item }">
        <span>{{ new Date(item.offer_date).toLocaleDateString() }}</span>
      </template>

      <template #item.status="{ item }">
        <VChip
          v-bind="resolveStatus(item.status)"
          density="default"
          label
          size="small"
        />
      </template>

      <template #item.actions="{ item }">
        <IconBtn>
          <VIcon icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList>
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
                :disabled="!item.can_accept || updatingOfferId === item.id"
                :title="!item.can_accept ? item.accept_disabled_reason || 'No disponible para aceptar' : undefined"
                @click="updateOfferStatus(item.id, 'accepted')"
              >
                Aceptar
              </VListItem>
              <VListItem
                value="reject"
                prepend-icon="tabler-x"
                :disabled="item.status === 'confirmed' || updatingOfferId === item.id"
                @click="updateOfferStatus(item.id, 'rejected')"
              >
                Rechazar
              </VListItem>
            </VList>
          </VMenu>
        </IconBtn>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalOffers"
        />
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDetailDialogOpen"
      max-width="500"
    >
      <VCard v-if="selectedOffer">
        <VCardTitle>Detalle de la oferta</VCardTitle>
        <VCardText>
          <div class="mb-3">
            <strong>Publicacion:</strong>
            <div>{{ selectedOffer.publication_title }}</div>
          </div>
          <div class="mb-3">
            <strong>Ofertante:</strong>
            <div>{{ selectedOffer.user_name }}</div>
          </div>
          <div class="mb-3">
            <strong>Monto:</strong>
            <div>{{ formatCurrency(selectedOffer.offer_amount) }}</div>
          </div>
          <div class="mb-3">
            <strong>Fecha:</strong>
            <div>{{ new Date(selectedOffer.offer_date).toLocaleString() }}</div>
          </div>
          <div class="mb-3">
            <strong>Estado:</strong>
            <div>{{ resolveStatus(selectedOffer.status).text }}</div>
          </div>
          <div
            v-if="selectedOffer.status === 'accepted_pending_confirmation'"
            class="mb-3"
          >
            <strong>Tiempo restante para confirmar:</strong>
            <div>{{ formatTimeRemaining(selectedOffer.time_to_expire) }}</div>
          </div>
          <div
            v-if="selectedOffer.accepted_at"
            class="mb-3"
          >
            <strong>Aceptada el:</strong>
            <div>{{ new Date(selectedOffer.accepted_at).toLocaleString() }}</div>
          </div>
          <div
            v-if="!selectedOffer.can_accept && ['pending', 'expired'].includes(selectedOffer.status)"
            class="mb-3"
          >
            <strong>Motivo para no aceptar:</strong>
            <div>{{ selectedOffer.accept_disabled_reason || 'Sin disponibilidad de stock.' }}</div>
          </div>
          <div>
            <strong>Comentario:</strong>
            <div v-if="selectedOffer.justification">
              {{ selectedOffer.justification }}
            </div>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Sin comentarios
            </div>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="isDetailDialogOpen = false"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
