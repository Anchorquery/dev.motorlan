<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/utils/formatCurrency'
import type { Publicacion } from '@/interfaces/publicacion'
import ChatModal from '@/components/ChatModal.vue'
import { useMotorFormatter } from '@/composables/useMotorFormatter'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const saleUuid = computed(() => route.params.uuid as string)
const { formatMotorName } = useMotorFormatter()

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
const motor = computed(() => sale.value?.publicacion || sale.value?.motor as Publicacion | null)
const buyer = computed(() => sale.value?.buyer || null)
const offer = computed(() => sale.value?.offer || null)

const getInitials = (name: string) => {
  const parts = name.split(' ')
  let initials = parts[0].substring(0, 1).toUpperCase()
  if (parts.length > 1) {
    initials += parts[parts.length - 1].substring(0, 1).toUpperCase()
  }
  return initials
}

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
    pending: { text: 'Venta Registrada', color: 'warning' },
    pendiente: { text: 'Acuerdo pendiente', color: 'warning' },
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

const isChatModalOpen = ref(false)

const goToChat = () => {
  if (sale.value?.uuid)
    isChatModalOpen.value = true
}

const goBack = () => {
  router.push({ name: 'dashboard-publications-sales' })
}

const refresh = () => {
  fetchSale()
}

const publicationTitle = computed(() => motor.value ? (formatMotorName(motor.value) || motor.value.title) : '')

const publicationImage = computed(() => {
  const img = motor.value?.imagen_destacada
  if (!img) return ''
  if (typeof img === 'string') return img
  if (Array.isArray(img) && img[0]) return (img[0] as any).url || ''
  return (img as any).url || ''
})

// Brands catalog to resolve acf.marca -> name
type BrandTerm = { term_id: number; name: string; slug: string }
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
const brands = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [Number(b.term_id), b])))

onMounted(() => { void fetchBrands() })

const resolveBrandName = (value: any): string => {
  if (!value) return '—'
  // If it's an object with name
  if (typeof value === 'object' && (value.name || value.label)) return value.name || value.label
  
  // If it's an ID
  const id = Number(value)
  if (Number.isFinite(id) && brandById.value[id]) return brandById.value[id].name
  
  // Fallback
  return String(value)
}
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <div class="px-6 pt-6 pb-4 bg-surface">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
          <div class="d-flex align-center gap-2 text-medium-emphasis mb-1">
             <VBtn
                variant="text"
                color="secondary"
                size="small"
                prepend-icon="tabler-arrow-left"
                class="px-2"
                @click="goBack"
              >
                Volver
              </VBtn>
              <VDivider vertical class="mx-1" />
              <span class="text-caption text-uppercase font-weight-bold tracking-wide">Detalle de Venta</span>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              variant="text"
              color="primary"
              size="small"
              prepend-icon="tabler-refresh"
              :loading="isFetching"
              @click="refresh"
            >
              {{ t('sales.refresh') }}
            </VBtn>
          </div>
        </div>

        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h1 class="text-h4 font-weight-bold text-high-emphasis mb-1">
              {{ t('sales.sale_detail_title') }}
            </h1>
            <div
              v-if="sale"
              class="d-flex flex-wrap align-center gap-x-4 gap-y-2 text-body-2 mt-2"
            >
              <VChip
                size="small"
                color="secondary"
                variant="tonal"
                class="font-weight-medium"
              >
                #{{ sale.uuid }}
              </VChip>

              <span v-if="sale.date_label" class="text-medium-emphasis d-flex align-center">
                <VIcon icon="tabler-calendar" size="16" class="mr-1" />
                {{ formatDate(sale.date, sale.date_label) }}
              </span>
              
              <div v-if="motor?.title" class="d-flex align-center text-medium-emphasis">
                  <VIcon icon="tabler-box" size="16" class="mr-1" />
                  <span class="text-truncate font-weight-medium text-high-emphasis" style="max-width: 300px;">
                    {{ formatMotorName(motor) || motor.title }}
                  </span>
              </div>

               <div v-if="buyer?.name" class="d-flex align-center text-medium-emphasis">
                  <VIcon icon="tabler-user" size="16" class="mr-1" />
                  <span>
                    Comprador: <span class="font-weight-medium text-high-emphasis">{{ buyer.name }}</span>
                  </span>
              </div>
            </div>
          </div>
          
           <div v-if="sale" class="d-flex align-center gap-3">
              <div class="text-end mr-2">
                <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Estado Actual</div>
                 <VChip
                    v-bind="resolveStatus(sale.status)"
                    variant="flat"
                    class="font-weight-bold mt-1"
                  />
              </div>
           </div>
        </div>
      </div>

      <VDivider />

      <VCardText class="pa-6 bg-background">
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
                <VCard border flat class="bg-surface rounded-xl overflow-hidden">
                  <VCardText class="pa-0">
                    <div class="px-5 py-4 bg-surface-variant-lighten d-flex align-center gap-2 border-b">
                      <VIcon icon="tabler-receipt-2" color="primary" />
                      <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-0">{{ t('sales.summary') }}</h3>
                    </div>
                    <VRow no-gutters>
                      <VCol cols="12" sm="6" class="border-e border-b">
                        <div class="pa-5">
                          <div class="d-flex align-center gap-2 mb-2">
                            <VIcon icon="tabler-currency-dollar" size="18" color="medium-emphasis" />
                            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">{{ t('sales.price') }}</span>
                          </div>
                          <div class="text-h4 text-primary font-weight-bold">
                            {{ formatCurrency(sale.price_value ?? sale.price) }}
                          </div>
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6" class="border-b">
                        <div class="pa-5">
                          <div class="d-flex align-center gap-2 mb-2">
                            <VIcon icon="tabler-calendar-event" size="18" color="medium-emphasis" />
                            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">{{ t('sales.date') }}</span>
                          </div>
                          <div class="text-body-1 text-high-emphasis font-weight-medium">
                            {{ formatDate(sale.date, sale.date_label) }}
                          </div>
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6" class="border-e">
                        <div class="pa-5">
                           <div class="d-flex align-center gap-2 mb-2">
                            <VIcon icon="tabler-tag" size="18" color="medium-emphasis" />
                            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Modalidad</span>
                          </div>
                          <div class="text-body-1 text-high-emphasis text-capitalize font-weight-medium">
                            {{ purchaseModeLabel }}
                          </div>
                        </div>
                      </VCol>
                      <VCol cols="12" sm="6">
                         <div class="pa-5">
                           <div class="d-flex align-center gap-2 mb-2">
                            <VIcon icon="tabler-activity" size="18" color="medium-emphasis" />
                            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">{{ t('sales.status') }}</span>
                          </div>
                           <div class="d-flex align-center gap-2">
                             <VBadge dot :color="resolveStatus(sale.status).color" inline />
                             <span class="text-body-1 font-weight-medium">{{ resolveStatus(sale.status).text }}</span>
                           </div>
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </div>



              <!-- 👉 Detalles de la Oferta -->
              <div v-if="offer" class="mb-6">
                 <VCard border flat class="bg-surface rounded-xl overflow-hidden">
                  <VCardText class="pa-0">
                    <div class="px-5 py-4 d-flex align-center gap-2 border-b" :class="offer.status === 'confirmed' ? 'bg-success-lighten-5' : 'bg-warning-lighten-5'">
                      <VIcon 
                        :icon="offer.status === 'confirmed' ? 'tabler-circle-check' : 'tabler-alert-circle'" 
                        :color="offer.status === 'confirmed' ? 'success' : 'warning'" 
                      />
                      <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-0">Detalles de la Oferta</h3>
                    </div>
                    
                    <div class="pa-5">
                      <VRow>
                        <VCol cols="12" sm="6">
                          <div class="d-flex align-center gap-2 mb-2">
                             <VIcon icon="tabler-coin" size="18" color="medium-emphasis" />
                             <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Monto Ofertado</span>
                          </div>
                          <div class="text-h5 font-weight-bold text-high-emphasis">
                            {{ formatCurrency(offer.offer_amount) }}
                          </div>
                        </VCol>
                        
                        <VCol cols="12" sm="6">
                           <div class="d-flex align-center gap-2 mb-2">
                             <VIcon icon="tabler-flag" size="18" color="medium-emphasis" />
                             <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Estado</span>
                          </div>
                          <VChip
                            :color="offer.status === 'confirmed' ? 'success' : 'warning'"
                            density="comfortable"
                            label
                            class="font-weight-bold"
                            variant="tonal"
                          >
                            {{ offer.status_label || offer.status }}
                          </VChip>
                        </VCol>
                        
                        <VCol v-if="offer.justification" cols="12">
                          <div class="bg-grey-lighten-4 rounded-lg pa-4 mt-2 position-relative">
                              <VIcon icon="tabler-quote" class="position-absolute top-0 left-0 mt-n2 ml-2 bg-grey-lighten-4" color="medium-emphasis" />
                              <div class="pl-4 border-s-lg border-primary">
                                <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold d-block mb-1">Mensaje del comprador</span>
                                <p class="text-body-1 font-italic text-high-emphasis mb-0">"{{ offer.justification }}"</p>
                              </div>
                          </div>
                        </VCol>
                      </VRow>
                    </div>
                  </VCardText>
                </VCard>
              </div>
            </VCol>

            <VCol cols="12" md="5">
              <!-- 👉 Información del Comprador -->
              <h3 class="text-h6 text-high-emphasis mb-4">{{ t('sales.buyer_information') }}</h3>
              <VCard border flat class="bg-surface rounded-xl h-100 d-flex flex-column">
                <VCardText class="pa-6 d-flex flex-column flex-grow-1">
                  <div class="d-flex align-center mb-6">
                    <VAvatar
                      color="primary-lighten-4"
                      variant="flat"
                      size="64"
                      class="mr-4 elevation-2"
                    >
                      <span class="text-h5 font-weight-bold text-primary">{{ buyer?.name ? getInitials(buyer.name) : 'U' }}</span>
                    </VAvatar>
                    
                    <div class="min-w-0">
                      <div class="text-h6 font-weight-bold text-high-emphasis text-truncate">
                        {{ buyer?.name || t('sales.no_buyer') }}
                      </div>
                      <div
                        v-if="buyer?.email"
                        class="text-body-2 text-medium-emphasis d-flex align-center gap-1"
                      >
                         <VIcon icon="tabler-mail" size="14" />
                         <span class="text-truncate">{{ buyer.email }}</span>
                      </div>
                      <div v-if="buyer?.phone" class="text-body-2 text-medium-emphasis d-flex align-center gap-1 mt-1">
                         <VIcon icon="tabler-phone" size="14" />
                         <span>{{ buyer.phone }}</span>
                      </div>
                    </div>
                  </div>

                  <VDivider class="mb-6 border-dashed" />

                  <div class="d-flex flex-column gap-4 flex-grow-1">
                    <!-- Moved Motor Info Here -->
                    <div v-if="motor" class="mb-6 flex-grow-1 d-flex flex-column justify-center">
                      <div class="d-flex align-center justify-space-between mb-3">
                           <div class="d-flex align-center gap-2">
                              <VIcon icon="tabler-box" color="primary" size="20" />
                              <h4 class="text-subtitle-1 font-weight-bold text-high-emphasis">{{ t('sales.motor_information') }}</h4>
                           </div>
                           <VBtn
                              variant="text"
                              color="primary"
                              size="small"
                              append-icon="tabler-external-link"
                              class="px-0"
                              @click="goToPublication"
                            >
                              {{ t('sales.view_publication') }}
                            </VBtn>
                      </div>

                      <div class="d-flex gap-4">
                         <VAvatar
                            rounded="lg"
                            size="72"
                            border
                            color="surface-variant"
                            class="flex-shrink-0"
                         >
                             <VImg
                                v-if="motor.imagen_destacada"
                                :src="typeof motor.imagen_destacada === 'string' ? motor.imagen_destacada : (motor.imagen_destacada as any)?.url"
                                cover
                              />
                              <VIcon v-else icon="tabler-motor" size="28" color="medium-emphasis" />
                         </VAvatar>
                         
                         <div class="flex-grow-1 min-w-0">
                             <h5 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-2 line-clamp-2" style="line-height: 1.3;">
                               {{ motor.title ? formatMotorName(motor) : motor.title }}
                             </h5>
                             
                             <div class="d-flex flex-wrap gap-2">
                                <div 
                                  v-if="motor.acf?.marca"
                                  class="d-flex align-center bg-grey-lighten-4 rounded px-2 py-1"
                                >
                                  <span class="text-caption text-medium-emphasis mr-1">{{ t('sales.motor_brand') }}:</span>
                                  <span class="text-caption font-weight-bold text-high-emphasis">{{ resolveBrandName(motor.acf.marca) }}</span>
                                </div>

                                <div 
                                  v-if="motor.acf?.potencia"
                                  class="d-flex align-center bg-grey-lighten-4 rounded px-2 py-1"
                                >
                                  <span class="text-caption text-medium-emphasis mr-1">{{ t('sales.motor_power') }}:</span>
                                  <span class="text-caption font-weight-bold text-high-emphasis">{{ motor.acf.potencia }} KW</span>
                                </div>
                             </div>
                         </div>
                      </div>
                    </div>
                    
                    <VDivider v-if="motor" class="mb-6 border-dashed" />


                    <div v-if="sale.payment_meta?.notas || sale.notes" class="d-flex align-start gap-3">
                       <VIcon icon="tabler-notebook" color="medium-emphasis" class="mt-1" />
                       <div class="flex-grow-1">
                          <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold d-block mb-1">{{ t('sales.notes') }}</span>
                          <div class="text-body-2 text-high-emphasis bg-grey-lighten-4 pa-3 rounded-lg">
                            {{ sale.payment_meta?.notas || sale.notes }}
                          </div>
                       </div>
                    </div>
                  </div>

                  <div class="mt-8">
                    <VBtn
                      block
                      color="primary"
                      size="large"
                      height="48"
                      prepend-icon="tabler-message-circle"
                      class="rounded-lg shadow-primary font-weight-bold"
                      @click="goToChat"
                    >
                      Ver chat con comprador
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VCardText>
    </VCard>

    <!-- Chat Modal -->
    <ChatModal
      v-if="sale?.uuid"
      v-model:is-open="isChatModalOpen"
      :purchase-uuid="sale.uuid"
      context-type="sale"
      :publication-title="publicationTitle"
      :publication-image="publicationImage"
    />
  </div>
</template>
