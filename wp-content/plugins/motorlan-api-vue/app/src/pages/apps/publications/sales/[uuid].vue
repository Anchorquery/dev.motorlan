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
    window.open(`/store/${motor.value.slug}`, '_blank', 'noopener,noreferrer')
  else if (motor.value?.uuid)
    router.push(`/apps/publications/publication/edit/${motor.value.uuid}`)
}

const goToChat = () => {
  if (sale.value?.uuid)
    router.push({ name: 'apps-purchases-chat-uuid', params: { uuid: sale.value.uuid } })
}

const goBack = () => {
  router.push({ name: 'apps-publications-sales' })
}

const refresh = () => {
  fetchSale()
}
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <div class="text-h6">
            {{ t('sales.sale_detail_title') }}
          </div>
          <div
            v-if="sale"
            class="text-body-2 text-medium-emphasis"
          >
            #{{ sale.uuid }}
            <span v-if="sale.date_label">• {{ formatDate(sale.date, sale.date_label) }}</span>
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
            variant="text"
            color="secondary"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ t('sales.back_to_list') }}
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <div
          v-if="isFetching"
          class="d-flex justify-center py-12"
        >
          <VProgressCircular
            color="primary"
            indeterminate
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
          <VRow class="gy-6">
            <VCol
              cols="12"
              md="7"
            >
              <!-- 👉 Resumen de Venta -->
              <VCard
                variant="outlined"
                class="mb-6"
              >
                <VCardTitle>{{ t('sales.summary') }}</VCardTitle>
                <VDivider />
                <VCardText>
                  <VRow class="gy-4">
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.price') }}
                      </div>
                      <div class="text-h6">
                        {{ formatCurrency(sale.price_value ?? sale.price) }}
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.date') }}
                      </div>
                      <div class="text-body-1">
                        {{ formatDate(sale.date, sale.date_label) }}
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.status') }}
                      </div>
                      <VChip
                        v-bind="resolveStatus(sale.status)"
                        class="mt-1"
                        density="comfortable"
                        label
                      >
                        {{ resolveStatus(sale.status).text }}
                      </VChip>
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        Modalidad
                      </div>
                      <div class="text-body-1">
                        {{ purchaseModeLabel }}
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- 👉 Información del Producto -->
              <VCard
                v-if="motor"
                variant="outlined"
                class="mb-6"
              >
                <VCardTitle>{{ t('sales.motor_information') }}</VCardTitle>
                <VDivider />
                <VCardText>
                  <VRow class="gy-4">
                    <VCol cols="12">
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.publication') }}
                      </div>
                      <div class="text-h6">
                        {{ motor.title }}
                      </div>
                    </VCol>
                    <VCol
                      v-if="motor.acf?.marca"
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.motor_brand') }}
                      </div>
                      <div class="text-body-1">
                        {{ motor.acf.marca?.name || motor.acf.marca }}
                      </div>
                    </VCol>
                    <VCol
                      v-if="motor.acf?.potencia"
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.motor_power') }}
                      </div>
                      <div class="text-body-1">
                        {{ motor.acf.potencia }}
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      class="d-flex gap-2 flex-wrap"
                    >
                      <VBtn
                        variant="tonal"
                        color="primary"
                        prepend-icon="tabler-eye"
                        @click="goToPublication"
                      >
                        {{ t('sales.view_publication') }}
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- 👉 Detalles de la Oferta -->
              <VCard
                v-if="offer"
                variant="outlined"
              >
                <VCardTitle>Detalles de la Oferta</VCardTitle>
                <VDivider />
                <VCardText>
                  <VRow class="gy-4">
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        Monto Ofertado
                      </div>
                      <div class="text-h6">
                        {{ formatCurrency(offer.offer_amount) }}
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        Estado Oferta
                      </div>
                      <VChip
                        :color="offer.status === 'confirmed' ? 'success' : 'warning'"
                        class="mt-1"
                        density="comfortable"
                        label
                      >
                        {{ offer.status_label || offer.status }}
                      </VChip>
                    </VCol>
                    <VCol
                      v-if="offer.justification"
                      cols="12"
                    >
                      <div class="text-caption text-medium-emphasis">
                        Mensaje del comprador
                      </div>
                      <p class="text-body-1 mt-1">
                        {{ offer.justification }}
                      </p>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              md="5"
            >
              <!-- 👉 Información del Comprador -->
              <VCard variant="outlined">
                <VCardTitle>{{ t('sales.buyer_information') }}</VCardTitle>
                <VDivider />
                <VCardText>
                  <div class="d-flex align-center mb-4">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      class="mr-3"
                    >
                      <VIcon icon="tabler-user" />
                    </VAvatar>
                    <div>
                      <div class="text-body-1 font-weight-medium">
                        {{ buyer?.name || t('sales.no_buyer') }}
                      </div>
                      <div
                        v-if="buyer?.email"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ buyer.email }}
                      </div>
                    </div>
                  </div>

                  <VDivider class="my-4" />

                  <div class="d-flex flex-column gap-4">
                    <div v-if="sale.payment_type">
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.payment_type') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.payment_type }}
                      </div>
                    </div>

                    <div v-if="sale.payment_meta?.notas || sale.notes">
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.notes') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.payment_meta?.notas || sale.notes }}
                      </div>
                    </div>
                  </div>

                  <VDivider class="my-4" />

                  <VBtn
                    block
                    color="primary"
                    prepend-icon="tabler-message-circle"
                    @click="goToChat"
                  >
                    Contactar Comprador
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VCardText>
    </VCard>
  </div>
</template>
