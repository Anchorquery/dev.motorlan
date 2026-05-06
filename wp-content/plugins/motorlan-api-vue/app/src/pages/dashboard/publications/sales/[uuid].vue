<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/utils/formatCurrency'
import type { Publicacion } from '@/interfaces/publicacion'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const saleUuid = computed(() => route.params.uuid as string)

const apiUrl = computed(() => `/wp-json/motorlan/v1/user/sale-details/${saleUuid.value}`)

const {
  data,
  execute: fetchSale,
  isFetching,
  error,
} = useApi<any>(apiUrl, { immediate: false }).get().json()

onMounted(() => {
  if (saleUuid.value)
    fetchSale()
})

watch(saleUuid, newId => {
  if (newId)
    fetchSale()
})

const sale = computed(() => data.value?.data || null)
const motor = computed(() => sale.value?.motor as Publicacion | null)
const buyer = computed(() => sale.value?.buyer || null)
const offer = computed(() => sale.value?.offer || null)

const formatDate = (value?: string, fallback?: string) => {
  if (!value)
    return fallback || '—'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime()))
    return fallback || value

  return parsed.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const resolveStatus = (status: string) => {
  const normalized = (status || '').toLowerCase()
  const statusMap: Record<string, { text: string; color: string }> = {
    completed: { text: t('sales.status_labels.completed'), color: 'success' },
    pending: { text: t('sales.status_labels.pending'), color: 'warning' },
    pendiente: { text: t('sales.status_labels.pending'), color: 'warning' },
    processing: { text: t('sales.status_labels.processing'), color: 'info' },
    cancelled: { text: t('sales.status_labels.cancelled'), color: 'error' },
    canceled: { text: t('sales.status_labels.cancelled'), color: 'error' },
    refunded: { text: t('sales.status_labels.refunded'), color: 'secondary' },
    expired: { text: t('sales.status_labels.expired'), color: 'warning' },
  }

  if (statusMap[normalized])
    return statusMap[normalized]

  if (!status)
    return { text: t('sales.status_labels.unknown'), color: 'secondary' }

  return { text: status.toUpperCase(), color: 'primary' }
}

const purchaseModeLabel = computed(() => {
  if (offer.value)
    return 'Venta por oferta'
  if (sale.value?.type === 'rent')
    return 'Alquiler'

  return 'Venta directa'
})

const goToPublication = () => {
  if (motor.value?.slug)
    window.open(`/${motor.value.slug}`, '_blank', 'noopener,noreferrer')
  else if ((motor.value as any)?.uuid)
    router.push(`/dashboard/publications/publication/edit/${(motor.value as any).uuid}`)
}

const goToChat = () => {
  if (sale.value?.uuid)
    router.push({ name: 'dashboard-purchases-chat-uuid', params: { uuid: sale.value.uuid } })
}

const goBack = () => {
  router.push({ name: 'dashboard-publications-sales' })
}

const refresh = () => {
  fetchSale()
}
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between flex-wrap gap-4">
        <div>
          <h2 class="text-h5 text-premium-title mb-1">
            {{ t('sales.sale_detail_title') }}
          </h2>
          <div
            v-if="sale"
            class="text-body-2 text-medium-emphasis d-flex align-center gap-2"
          >
            <span class="font-weight-medium">#{{ sale.uuid }}</span>
            <span v-if="sale.date_label" class="text-disabled">•</span>
            <span v-if="sale.date_label">{{ formatDate(sale.date, sale.date_label) }}</span>
          </div>
        </div>
        <div class="d-flex align-center gap-2">
          <VBtn
            variant="text"
            color="primary"
            prepend-icon="tabler-refresh"
            :loading="isFetching"
            @click="refresh"
          >
            {{ t('sales.refresh') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ t('sales.back_to_list') }}
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div
          v-if="isFetching"
          class="d-flex justify-center py-12"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="48"
          />
        </div>

        <VAlert
          v-else-if="error"
          type="error"
          border="start"
          variant="tonal"
          class="mb-6"
        >
          {{ t('sales.detail_error') }}
        </VAlert>

        <VAlert
          v-else-if="!sale"
          type="info"
          border="start"
          variant="tonal"
        >
          {{ t('sales.detail_not_found') }}
        </VAlert>

        <template v-else>
          <VRow>
            <VCol cols="12" md="7">
              <!-- 👉 Resumen de Venta -->
              <div class="mb-6">
                <h3 class="text-h6 text-high-emphasis mb-4">{{ t('sales.summary') }}</h3>
                <VCard variant="outlined" class="bg-surface">
                  <VCardText class="pa-5">
                    <VRow>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.price') }}
                        </div>
                        <div class="text-h5 text-primary font-weight-bold">
                          {{ formatCurrency(sale.price_value ?? sale.price) }}
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.date') }}
                        </div>
                        <div class="text-body-1 text-high-emphasis">
                          {{ formatDate(sale.date, sale.date_label) }}
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.status') }}
                        </div>
                        <VChip
                          v-bind="resolveStatus(sale.status)"
                          density="comfortable"
                          label
                          class="font-weight-medium"
                        />
                      </VCol>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          Modalidad
                        </div>
                        <div class="text-body-1 text-high-emphasis text-capitalize">
                          {{ purchaseModeLabel }}
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </div>

              <!-- 👉 Información del Producto -->
              <div v-if="motor" class="mb-6">
                <h3 class="text-h6 text-high-emphasis mb-4">{{ t('sales.motor_information') }}</h3>
                <VCard variant="outlined" class="bg-surface">
                  <VCardText class="pa-5">
                    <VRow>
                      <VCol cols="12">
                         <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.publication') }}
                        </div>
                        <div class="text-h6 text-high-emphasis mb-2">
                          {{ motor.title }}
                        </div>
                         <VBtn
                            variant="tonal"
                            color="primary"
                            size="small"
                            prepend-icon="tabler-eye"
                            @click="goToPublication"
                          >
                            {{ t('sales.view_publication') }}
                          </VBtn>
                      </VCol>
                      <VDivider class="my-3 border-dashed" />
                      <VCol v-if="motor.acf?.marca" cols="12" sm="6" class="pt-0">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.motor_brand') }}
                        </div>
                        <div class="text-body-1 text-high-emphasis">
                          {{ (motor.acf.marca as any)?.name || motor.acf.marca }}
                        </div>
                      </VCol>
                      <VCol v-if="motor.acf?.potencia" cols="12" sm="6" class="pt-0">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          {{ t('sales.motor_power') }}
                        </div>
                        <div class="text-body-1 text-high-emphasis">
                          {{ motor.acf.potencia }}
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </div>

              <!-- 👉 Detalles de la Oferta -->
              <div v-if="offer">
                <h3 class="text-h6 text-high-emphasis mb-4">Detalles de la Oferta</h3>
                 <VCard variant="outlined" class="bg-surface">
                  <VCardText class="pa-5">
                    <VRow>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          Monto Ofertado
                        </div>
                        <div class="text-h6 text-high-emphasis">
                          {{ formatCurrency(offer.offer_amount) }}
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6">
                        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">
                          Estado Oferta
                        </div>
                        <VChip
                          :color="offer.status === 'confirmed' ? 'success' : 'warning'"
                          density="comfortable"
                          label
                          class="font-weight-medium"
                        >
                          {{ offer.status_label || offer.status }}
                        </VChip>
                      </VCol>
                      <VCol v-if="offer.justification" cols="12">
                        <div class="bg-light rounded pa-3">
                            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold d-block mb-1">Mensaje del comprador</span>
                             <p class="text-body-2 text-high-emphasis mb-0">"{{ offer.justification }}"</p>
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </div>
            </VCol>

            <VCol cols="12" md="5">
              <!-- 👉 Información del Comprador -->
              <h3 class="text-h6 text-high-emphasis mb-4">{{ t('sales.buyer_information') }}</h3>
              <VCard variant="outlined" class="bg-surface h-100">
                <VCardText class="pa-5 d-flex flex-column h-100">
                  <div class="d-flex align-center mb-6">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="48"
                      class="mr-4"
                    >
                      <VIcon icon="tabler-user" size="28" />
                    </VAvatar>
                    <div>
                      <div class="text-h6 font-weight-medium">
                        {{ buyer?.name || t('sales.no_buyer') }}
                      </div>
                      <div
                        v-if="buyer?.email"
                        class="text-body-2 text-medium-emphasis"
                      >
                        {{ buyer.email }}
                      </div>
                    </div>
                  </div>

                  <VDivider class="mb-6 border-dashed" />

                  <div class="d-flex flex-column gap-4 flex-grow-1">
                    <div v-if="sale.payment_type" class="d-flex justify-space-between align-center">
                       <span class="text-body-2 text-medium-emphasis">{{ t('sales.payment_type') }}</span>
                       <span class="text-body-1 font-weight-medium text-high-emphasis">{{ sale.payment_type }}</span>
                    </div>

                    <div v-if="sale.payment_meta?.notas || sale.notes">
                       <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold d-block mb-1">{{ t('sales.notes') }}</span>
                       <div class="text-body-2 text-high-emphasis bg-light pa-3 rounded">
                         {{ sale.payment_meta?.notas || sale.notes }}
                       </div>
                    </div>
                  </div>

                  <div class="mt-6 pt-auto">
                    <VBtn
                      block
                      color="primary"
                      prepend-icon="tabler-message-circle"
                      @click="goToChat"
                      class="mb-0"
                    >
                      Contactar Comprador
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VCardText>
    </VCard>
  </div>
</template>
