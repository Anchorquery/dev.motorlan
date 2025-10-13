<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const saleId = computed(() => route.params.id as string)

const apiUrl = computed(() => `/wp-json/motorlan/v1/user/sales/${saleId.value}`)

const {
  data,
  execute: fetchSale,
  isFetching,
  error,
} = useApi<any>(apiUrl, { immediate: false }).get().json()

onMounted(() => {
  if (saleId.value)
    fetchSale()
})

watch(saleId, newId => {
  if (newId)
    fetchSale()
})

const sale = computed(() => data.value?.data || null)

const formatCurrency = (value: number | string | null | undefined) => {
  const numericValue = Number(value)
  if (Number.isNaN(numericValue))
    return value ?? '—'

  return new Intl.NumberFormat('es-VE', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numericValue)
}

const formatDate = (value?: string, fallback?: string) => {
  if (!value) {
    if (!fallback)
      return '—'
    return fallback
  }

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
  if (normalized === 'completed')
    return { text: t('sales.status_labels.completed'), color: 'success' }
  if (normalized === 'pendiente' || normalized === 'pending')
    return { text: t('sales.status_labels.pending'), color: 'warning' }
  if (normalized === 'processing')
    return { text: t('sales.status_labels.processing'), color: 'info' }
  if (normalized === 'cancelled' || normalized === 'canceled')
    return { text: t('sales.status_labels.cancelled'), color: 'error' }
  if (normalized === 'refunded')
    return { text: t('sales.status_labels.refunded'), color: 'secondary' }
  if (normalized === 'expired')
    return { text: t('sales.status_labels.expired'), color: 'warning' }

  if (!status)
    return { text: t('sales.status_labels.unknown'), color: 'secondary' }

  return { text: status.toUpperCase(), color: 'primary' }
}

const resolveType = (type: string) => {
  if ((type || '').toLowerCase() === 'rent')
    return t('sales.type_options.rent')

  return t('sales.type_options.sale')
}

const goToPublication = () => {
  if (!sale.value)
    return

  if (sale.value.publication_slug) {
    window.open(`/store/${sale.value.publication_slug}`, '_blank', 'noopener,noreferrer')
    return
  }

  if (sale.value.publication_uuid)
    router.push(`/apps/publications/publication/edit/${sale.value.publication_uuid}`)
}

const goBack = () => {
  router.back()
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
            #{{ sale.id }}
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
                        {{ t('sales.type') }}
                      </div>
                      <div class="text-body-1">
                        {{ resolveType(sale.type) }}
                      </div>
                    </VCol>
                    <VCol cols="12">
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.sale_identifier') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.uuid || sale.id }}
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <VCard
                v-if="sale.motor"
                variant="outlined"
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
                        {{ sale.motor.title }}
                      </div>
                    </VCol>
                    <VCol
                      v-if="sale.motor.acf?.marca"
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.motor_brand') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.motor.acf.marca?.name || sale.motor.acf.marca }}
                      </div>
                    </VCol>
                    <VCol
                      v-if="sale.motor.acf?.potencia"
                      cols="12"
                      sm="6"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.motor_power') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.motor.acf.potencia }}
                      </div>
                    </VCol>
                    <VCol
                      cols="12"
                      class="d-flex gap-2 flex-wrap"
                    >
                      <VBtn
                        variant="text"
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
            </VCol>

            <VCol
              cols="12"
              md="5"
            >
              <VCard variant="outlined">
                <VCardTitle>{{ t('sales.buyer_information') }}</VCardTitle>
                <VDivider />
                <VCardText>
                  <div class="d-flex flex-column gap-3">
                    <div>
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.buyer_name') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.buyer?.name || t('sales.no_buyer') }}
                      </div>
                    </div>

                    <div v-if="sale.buyer?.email">
                      <div class="text-caption text-medium-emphasis">
                        {{ t('sales.buyer_email') }}
                      </div>
                      <div class="text-body-1">
                        {{ sale.buyer.email }}
                      </div>
                    </div>

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
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VCardText>
    </VCard>
  </div>
</template>
