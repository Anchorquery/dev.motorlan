<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'

const route = useRoute()
const uuid = route.params.uuid as string

const purchase = ref()
const isPurchaseLoading = ref(true)
const purchaseError = ref<string | null>(null)
const { get } = useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`))

const fetchPurchase = async () => {
  isPurchaseLoading.value = true
  purchaseError.value = null

  try {
    const { data, error } = await get().json()

    if (error.value) {
      purchase.value = undefined
      purchaseError.value = error.value.message || 'No pudimos cargar la información de la compra.'
      return
    }

    purchase.value = data.value?.data
  }
  catch (err: any) {
    purchase.value = undefined
    purchaseError.value = err?.message || 'Ocurrió un error al consultar la compra.'
  }
  finally {
    isPurchaseLoading.value = false
  }
}

void fetchPurchase()

const opinion = ref({ rating: 0, comment: '' })
const isSubmittingOpinion = ref(false)
const opinionSuccess = ref(false)
const opinionError = ref<string | null>(null)

const motorAcf = computed(() => (purchase.value?.motor?.acf || {}) as Record<string, any>)
const sellerAcf = computed(() => (purchase.value?.motor?.author?.acf || {}) as Record<string, any>)

const formatProductTitle = (publication?: Publicacion | null) => {
  if (!publication)
    return ''

  const acf = (publication.acf || {}) as Record<string, any>
  const parts = [
    publication.title,
    acf.tipo_o_referencia,
    acf.potencia ? `${acf.potencia} kW` : null,
    acf.velocidad ? `${acf.velocidad} rpm` : null,
  ].filter(Boolean) as string[]

  return parts.join(' ').toUpperCase()
}

const productTitle = computed(() => {
  const motor = purchase.value?.motor as Publicacion | null | undefined
  const formatted = formatProductTitle(motor)

  if (formatted)
    return formatted

  if (purchase.value?.title)
    return String(purchase.value.title).toUpperCase()

  return 'PRODUCTO'
})

const productTypeLabel = computed(() => {
  const types: string[] = []

  const pushCandidate = (candidate: unknown) => {
    if (!candidate)
      return

    if (typeof candidate === 'string') {
      types.push(candidate)
      return
    }

    if (Array.isArray(candidate)) {
      candidate.forEach(inner => pushCandidate(inner))
      return
    }

    if (typeof candidate === 'object') {
      const name = (candidate as Record<string, any>).name || (candidate as Record<string, any>).label
      if (name)
        types.push(String(name))
    }
  }

  const motor = purchase.value?.motor as Publicacion | null | undefined

  if (Array.isArray(motor?.tipo)) {
    motor?.tipo.forEach(typeItem => pushCandidate(typeItem))
  }

  pushCandidate(motorAcf.value.tipo)
  pushCandidate(motorAcf.value.tipo_producto)

  const normalized = types
    .map(name => name.trim())
    .filter(Boolean)

  const match = (keyword: string) => normalized.find(name => name.toLowerCase().includes(keyword))

  if (match('regulador'))
    return 'Regulador'
  if (match('repuesto'))
    return 'Repuesto'
  if (match('motor'))
    return 'Motor'

  return normalized[0] || null
})
const productImage = computed(() => {
  const image = purchase.value?.motor?.imagen_destacada
  if (!image)
    return null
  if (typeof image === 'string')
    return image
  if (Array.isArray(image))
    return image[0]?.url || null
  return image.url || null
})

const productSlug = computed(() => purchase.value?.motor?.slug)
const productLink = computed(() => {
  if (!productSlug.value)
    return null

  return { name: 'store-slug', params: { slug: productSlug.value } }
})

const locationLabel = computed(() => {
  const country = motorAcf.value.pais
  const province = motorAcf.value.provincia

  if (country && province)
    return `${province}, ${country}`

  return country || province || null
})

const sellerCompany = computed(() => {
  const company = sellerAcf.value.empresa ?? sellerAcf.value.company
  return company ? String(company) : null
})

const sellerRating = computed(() => {
  const rating = Number(sellerAcf.value.calificacion ?? sellerAcf.value.rating)
  return Number.isFinite(rating) ? rating : null
})

const sellerRatingLabel = computed(() => {
  if (sellerRating.value === null)
    return null

  return sellerRating.value.toFixed(1)
})

const sellerSales = computed(() => {
  const sales = Number(sellerAcf.value.ventas ?? sellerAcf.value.sales)
  return Number.isFinite(sales) ? sales : null
})

const sellerSalesLabel = computed(() => {
  if (sellerSales.value === null)
    return null

  return new Intl.NumberFormat('es-VE').format(sellerSales.value)
})

const sellerSalesText = computed(() => {
  if (sellerSales.value === null)
    return null

  const label = sellerSalesLabel.value ?? String(sellerSales.value)
  return `${label} ${sellerSales.value === 1 ? 'venta' : 'ventas'}`
})

const sellerPhone = computed(() => {
  const phone = sellerAcf.value.telefono ?? sellerAcf.value.phone
  return phone ? String(phone) : null
})

const sellerWhatsapp = computed(() => {
  const whatsapp = sellerAcf.value.whatsapp ?? sellerAcf.value.whatsapp_number
  return whatsapp ? String(whatsapp) : null
})

const sellerEmail = computed(() => {
  const authorEmail = purchase.value?.motor?.author?.email
  const fallbackEmail = sellerAcf.value.email
  const resolved = authorEmail || fallbackEmail

  return resolved ? String(resolved) : null
})

const sellerHasContact = computed(() => Boolean(sellerPhone.value || sellerWhatsapp.value || sellerEmail.value))

const normalizePhone = (value: string) => value.replace(/[^\d+]/g, '')

const sellerPhoneHref = computed(() => {
  if (!sellerPhone.value)
    return null

  const normalized = normalizePhone(sellerPhone.value)
  return normalized ? `tel:${normalized}` : null
})

const sellerWhatsappHref = computed(() => {
  if (!sellerWhatsapp.value)
    return null

  const normalized = normalizePhone(sellerWhatsapp.value).replace(/^\+/, '')
  return normalized ? `https://wa.me/${normalized}` : null
})

const hasSellerMetrics = computed(() => sellerRating.value !== null || sellerSalesLabel.value !== null)

const messagesRoute = computed(() => ({
  name: 'apps-purchases-chat-uuid',
  params: { uuid },
}))

const quantityValue = computed(() => {
  const raw = (purchase.value as any)?.cantidad ?? (purchase.value as any)?.quantity ?? 1
  const qty = Number(raw) || 1
  return qty > 0 ? qty : 1
})

const quantityLabel = computed(() => {
  const qty = quantityValue.value

  return `${qty} ${qty === 1 ? 'unidad' : 'unidades'}`
})

const formatCurrency = (value: unknown) => {
  if (value === null || value === undefined || value === '')
    return null

  if (typeof value === 'number') {
    return new Intl.NumberFormat('es-VE', {
      style: 'currency',
      currency: 'VES',
      minimumFractionDigits: 2,
    }).format(value)
  }

  if (typeof value === 'string') {
    const normalized = value.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')
    const parsed = Number(normalized)

    if (!Number.isNaN(parsed)) {
      return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: 'VES',
        minimumFractionDigits: 2,
      }).format(parsed)
    }

    return `Bs. ${value}`
  }

  return null
}

const toNumericValue = (value: unknown): number | null => {
  if (typeof value === 'number' && Number.isFinite(value))
    return value

  if (typeof value === 'string') {
    const normalized = value.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')
    const parsed = Number(normalized)
    if (!Number.isNaN(parsed))
      return parsed
  }

  return null
}

const formatStatusLabel = (status: string) => status
  .split(/[_-]/g)
  .filter(Boolean)
  .map(part => part.charAt(0).toUpperCase() + part.slice(1))
  .join(' ')

const offerDetails = computed(() => purchase.value?.offer ?? null)
const hasOffer = computed(() => Boolean(offerDetails.value))

const offerAmountValue = computed(() => toNumericValue((offerDetails.value as any)?.offer_amount))
const offerAmountLabel = computed(() => (offerAmountValue.value !== null ? formatCurrency(offerAmountValue.value) : null))

const publishedPriceValue = computed(() => {
  const fromPurchase = toNumericValue((purchase.value as any)?.precio_publicado)
  if (fromPurchase !== null)
    return fromPurchase

  return toNumericValue(motorAcf.value.precio_de_venta)
})

const publishedPriceLabel = computed(() => (publishedPriceValue.value !== null ? formatCurrency(publishedPriceValue.value) : null))

const purchaseTotalValue = computed(() => {
  const purchaseAmount = toNumericValue((purchase.value as any)?.precio_compra)
  if (purchaseAmount !== null)
    return purchaseAmount

  if (offerAmountValue.value !== null)
    return offerAmountValue.value

  if (publishedPriceValue.value !== null)
    return publishedPriceValue.value * quantityValue.value

  return null
})

const unitPriceValue = computed(() => {
  if (purchaseTotalValue.value !== null) {
    const qty = quantityValue.value || 1
    return purchaseTotalValue.value / qty
  }

  return publishedPriceValue.value
})

const unitPriceLabel = computed(() => {
  if (unitPriceValue.value !== null)
    return formatCurrency(unitPriceValue.value) || 'Consultar precio'

  return 'Consultar precio'
})

const totalPriceLabel = computed(() => {
  if (purchaseTotalValue.value !== null)
    return formatCurrency(purchaseTotalValue.value) || unitPriceLabel.value

  if (unitPriceValue.value !== null) {
    const total = unitPriceValue.value * quantityValue.value
    return formatCurrency(total) || unitPriceLabel.value
  }

  return 'Consultar precio'
})

const summaryPriceNotes = computed(() => {
  const notes: string[] = []

  if (quantityValue.value > 1) {
    if (unitPriceValue.value !== null) {
      const unitLabel = formatCurrency(unitPriceValue.value) || ''
      notes.push(`${quantityLabel.value} · ${unitLabel} c/u`.trim())
    }
    else {
      notes.push(quantityLabel.value)
    }
  }

  if (publishedPriceValue.value !== null && unitPriceValue.value !== null) {
    const published = publishedPriceValue.value
    if (Math.abs(unitPriceValue.value - published) > 0.009) {
      const publishedLabel = formatCurrency(published)
      if (publishedLabel)
        notes.push(`Precio publicado: ${publishedLabel}`)
    }
  }

  return notes
})

const formatDateTime = (value: string | null | undefined) => {
  if (!value)
    return null

  const normalized = value.includes('T') ? value : value.replace(' ', 'T')
  const date = new Date(normalized)

  if (Number.isNaN(date.getTime()))
    return value

  return new Intl.DateTimeFormat('es-VE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

const offerDateLabel = computed(() => formatDateTime((offerDetails.value as any)?.offer_date))
const offerAcceptedLabel = computed(() => formatDateTime((offerDetails.value as any)?.accepted_at))
const offerConfirmedLabel = computed(() => formatDateTime((offerDetails.value as any)?.confirmed_at))
const offerExpiresLabel = computed(() => formatDateTime((offerDetails.value as any)?.expires_at))

const offerStatusInfo = computed(() => {
  const status = String((offerDetails.value as any)?.status || '').toLowerCase()
  if (!status)
    return null

  const map: Record<string, { label: string; color: 'success' | 'warning' | 'info' | 'error'; description?: string }> = {
    pending: {
      label: 'Pendiente',
      color: 'warning',
      description: 'El vendedor aun no responde a la oferta.',
    },
    accepted_pending_confirmation: {
      label: 'Aceptada por confirmar',
      color: 'info',
      description: 'La oferta fue aceptada. Queda pendiente tu confirmacion de compra.',
    },
    confirmed: {
      label: 'Confirmada',
      color: 'success',
      description: 'La oferta se confirmo y se registro la compra.',
    },
    rejected: {
      label: 'Rechazada',
      color: 'error',
      description: 'El vendedor rechazo esta oferta.',
    },
    expired: {
      label: 'Expirada',
      color: 'warning',
      description: 'La oferta vencio sin confirmacion.',
    },
  }

  const fallback = {
    label: formatStatusLabel(status),
    color: 'info' as const,
  }

  return map[status] || fallback
})

const offerJustification = computed(() => {
  const justification = (offerDetails.value as any)?.justification
  if (typeof justification === 'string' && justification.trim())
    return justification.trim()
  return null
})

const isNegotiable = computed(() => {
  const negotiable = motorAcf.value.precio_negociable

  if (typeof negotiable === 'string') {
    const normalized = negotiable.trim().toLowerCase()
    return normalized === 'si' || normalized === 'yes' || normalized === 'true'
  }

  return Boolean(negotiable)
})

const purchaseModeLabel = computed(() => {
  if (hasOffer.value)
    return 'Compra por oferta'

  return isNegotiable.value ? 'Oferta negociable' : 'Compra directa'
})

const purchaseModeDescription = computed(() => {
  if (hasOffer.value) {
    if (offerAmountLabel.value)
      return `Compra concretada mediante una oferta aceptada por ${offerAmountLabel.value}.`

    return 'Compra concretada mediante una oferta aceptada.'
  }

  return isNegotiable.value
    ? 'El vendedor marco este articulo como negociable. Revisa el acuerdo con el vendedor.'
    : 'Compra realizada al precio publicado sin negociacion.'
})

const statusRawLabel = computed(() => {
  const raw = purchase.value?.estado
  if (typeof raw !== 'string' || raw.trim() === '')
    return null

  return formatStatusLabel(raw)
})

const statusInfo = computed(() => {
  const rawStatus = typeof purchase.value?.estado === 'string' ? purchase.value.estado : ''
  const status = rawStatus.toLowerCase()
  const date = purchase.value?.fecha_compra
  const withDate = (text: string) => (date ? `El ${date} ${text}` : text)

  const map: Record<string, { label: string; title: string; description: string; tone: 'success' | 'warning' | 'info' | 'error' }> = {
    entregado: {
      label: 'Entregado',
      title: 'Recibiste la compra',
      description: withDate('confirmamos que ya la tienes.'),
      tone: 'success',
    },
    enviado: {
      label: 'Enviado',
      title: 'Tu compra va en camino',
      description: withDate('salio a entrega.'),
      tone: 'info',
    },
    en_proceso: {
      label: 'En proceso',
      title: 'Estamos preparando tu compra',
      description: withDate('estamos gestionando el envio.'),
      tone: 'info',
    },
    pendiente: {
      label: 'Pendiente',
      title: 'Tu pago esta pendiente',
      description: withDate('registramos tu orden. Te avisaremos cuando avance.'),
      tone: 'warning',
    },
    cancelado: {
      label: 'Cancelado',
      title: 'La compra se cancelo',
      description: withDate('anulamos el pedido.'),
      tone: 'error',
    },
  }

  if (map[status])
    return map[status]

  const fallbackLabel = statusRawLabel.value || 'En progreso'

  return {
    label: fallbackLabel,
    title: `Estado: ${fallbackLabel}`,
    description: withDate('seguimos gestionando tu pedido.'),
    tone: 'info',
  }
})

const statusSummaryLabel = computed(() => statusRawLabel.value || statusInfo.value.label)

const sellerName = computed(() => purchase.value?.motor?.author?.name || 'Vendedor')
const sellerAvatar = computed(() => {
  const avatar = purchase.value?.motor?.author?.acf?.avatar

  if (!avatar)
    return null

  if (typeof avatar === 'string')
    return avatar

  return avatar.url || null
})

const sellerInitials = computed(() => {
  const parts = sellerName.value.split(' ').filter(Boolean)

  return parts.slice(0, 2).map((part: string) => part[0]?.toUpperCase()).join('') || 'V'
})

const helpItems = [
  { id: 'contact', title: 'Como me pongo en contacto con el vendedor?' },
  { id: 'delivery', title: 'Necesito ayuda con envio y entrega' },
  { id: 'payment', title: 'Tengo preguntas sobre el pago' },
]

const sendOpinion = async () => {
  if (isSubmittingOpinion.value)
    return

  opinionError.value = null

  try {
    isSubmittingOpinion.value = true
    await useApi(`/wp-json/motorlan/v1/purchases/${uuid}/opinion`).post(opinion.value)
    opinionSuccess.value = true
    opinion.value = { rating: 0, comment: '' }
    setTimeout(() => {
      opinionSuccess.value = false
    }, 4000)
  }
  catch (error) {
    console.error(error)
    opinionError.value = 'No pudimos guardar tu opinion. Intenta de nuevo mas tarde.'
  }
  finally {
    isSubmittingOpinion.value = false
  }
}
</script>

<template>
  <VContainer
    v-if="isPurchaseLoading"
    class="purchase-page purchase-page__loading"
  >
    <div class="purchase-page__loading-indicator">
      <VProgressCircular
        color="primary"
        indeterminate
        size="32"
        width="3"
      />
    </div>
  </VContainer>

  <VContainer
    v-else-if="purchase"
    class="purchase-page"
  >
    <div class="purchase-breadcrumbs">
      <RouterLink
        class="purchase-breadcrumbs__link"
        :to="{ name: 'apps-purchases-purchases' }"
      >
        Compras
      </RouterLink>
      <span class="purchase-breadcrumbs__separator">/</span>
      <span class="purchase-breadcrumbs__current">Estado de la compra</span>
    </div>

    <VRow
      class="purchase-layout"
      align="start"
      dense
    >
      <VCol
        cols="12"
        md="8"
        lg="9"
      >
        <div class="purchase-main">
          <VCard class="purchase-card summary-card">
          <VCardText>
              <div class="summary-header">
                <div class="summary-info">
                  <h1 class="summary-title">
                    <RouterLink
                      v-if="productLink"
                      :to="productLink"
                      class="summary-title__link"
                    >
                      {{ productTitle }}
                    </RouterLink>
                    <span v-else>{{ productTitle }}</span>
                  </h1>
                  <div class="summary-meta">
                    <span>{{ quantityLabel }}</span>
                    <span v-if="productLink">|</span>
                    <RouterLink
                      v-if="productLink"
                      class="summary-link"
                      :to="productLink"
                    >
                      Ver detalle
                    </RouterLink>
                  </div>
                  <div
                    v-if="locationLabel"
                    class="summary-location"
                  >
                    <VIcon
                      icon="mdi-map-marker"
                      size="18"
                      class="mr-1"
                    />
                      <span>{{ locationLabel }}</span>
                  </div>
                  <div
                    v-if="statusSummaryLabel || purchaseModeLabel || productTypeLabel"
                    class="summary-tags"
                  >
                    <span
                      v-if="productTypeLabel"
                      class="summary-tag"
                    >
                      {{ productTypeLabel }}
                    </span>
                    <span
                      v-if="statusSummaryLabel"
                      class="summary-tag"
                    >
                      {{ statusSummaryLabel }}
                    </span>
                    <span
                      v-if="purchaseModeLabel"
                      class="summary-tag summary-tag--highlight"
                    >
                      {{ purchaseModeLabel }}
                    </span>
                  </div>
                  <div class="summary-price">
                    <span class="summary-price__label">Precio</span>
                    <span class="summary-price__value">{{ totalPriceLabel }}</span>
                    <span
                      v-for="note in summaryPriceNotes"
                      :key="note"
                      class="summary-price__note"
                    >
                      {{ note }}
                    </span>
                  </div>
                </div>
                <VAvatar
                  v-if="productImage"
                  :image="productImage"
                  size="64"
                  class="summary-avatar"
                />
                <VAvatar
                  v-else
                  size="64"
                  class="summary-avatar summary-avatar--placeholder"
                >
                  <VIcon
                    icon="mdi-image-off"
                    size="28"
                  />
                </VAvatar>
              </div>
            </VCardText>
          </VCard>

          <VCard class="purchase-card seller-card">
            <VCardTitle>Datos del vendedor</VCardTitle>
            <VCardText>
              <div class="seller-card__header">
                <VAvatar
                  v-if="sellerAvatar"
                  :image="sellerAvatar"
                  size="56"
                  class="mr-4"
                />
                <VAvatar
                  v-else
                  size="56"
                  color="primary"
                  class="mr-4"
                >
                  <span class="avatar-initials">{{ sellerInitials }}</span>
                </VAvatar>
                <div class="seller-card__identity">
                  <div class="seller-card__name">{{ sellerName }}</div>
                  <div
                    v-if="sellerCompany"
                    class="seller-card__company"
                  >
                    {{ sellerCompany }}
                  </div>
                  <div
                    v-if="locationLabel"
                    class="seller-card__location"
                  >
                    <VIcon
                      icon="mdi-map-marker"
                      size="18"
                      class="mr-1"
                    />
                    <span>{{ locationLabel }}</span>
                  </div>
                </div>
              </div>
              <div
                v-if="hasSellerMetrics"
                class="seller-card__metrics"
              >
                <div
                  v-if="sellerRating !== null"
                  class="seller-card__metrics-item"
                >
                  <VRating
                    :model-value="sellerRating"
                    color="warning"
                    density="compact"
                    half-increments
                    readonly
                    size="18"
                    class="mr-2"
                  />
                  <span>{{ sellerRatingLabel }}</span>
                </div>
                <div
                  v-if="sellerSalesText"
                  class="seller-card__metrics-item"
                >
                  <VIcon
                    icon="mdi-store"
                    size="18"
                    class="mr-1"
                  />
                  <span>{{ sellerSalesText }}</span>
                </div>
              </div>
              <div
                v-if="sellerHasContact"
                class="seller-card__contact"
              >
                <a
                  v-if="sellerPhone && sellerPhoneHref"
                  :href="sellerPhoneHref"
                  class="seller-card__contact-item"
                >
                  <VIcon
                    icon="mdi-phone"
                    size="18"
                    class="mr-1"
                  />
                  {{ sellerPhone }}
                </a>
                <a
                  v-if="sellerWhatsapp && sellerWhatsappHref"
                  :href="sellerWhatsappHref"
                  target="_blank"
                  rel="noopener"
                  class="seller-card__contact-item"
                >
                  <VIcon
                    icon="mdi-whatsapp"
                    size="18"
                    class="mr-1"
                  />
                  {{ sellerWhatsapp }}
                </a>
                <a
                  v-if="sellerEmail"
                  :href="`mailto:${sellerEmail}`"
                  class="seller-card__contact-item"
                >
                  <VIcon
                    icon="mdi-email-outline"
                    size="18"
                    class="mr-1"
                  />
                  {{ sellerEmail }}
                </a>
              </div>
              <div class="seller-card__actions">
                <VBtn
                  v-if="productLink"
                  :to="productLink"
                  color="primary"
                  variant="tonal"
                >
                  Ver publicacion
                </VBtn>
                <VBtn
                  :to="messagesRoute"
                  color="secondary"
                  variant="text"
                >
                  Abrir chat
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <VCard class="purchase-card status-card">
            <VCardText>
              <div :class="['status-chip', `status-chip--${statusInfo.tone}`]">
                {{ statusInfo.label }}
              </div>
              <h2 class="status-title">
                {{ statusInfo.title }}
              </h2>
              <p class="status-description">
                {{ statusInfo.description }}
              </p>
              <p
                v-if="statusRawLabel"
                class="status-description status-description--muted"
              >
                Estado reportado: {{ statusRawLabel }}
              </p>
              <p
                v-if="purchaseModeDescription"
                class="status-description status-description--muted"
              >
                {{ purchaseModeDescription }}
              </p>
              <div
                v-if="productLink"
                class="status-actions"
              >
                <VBtn
                  color="primary"
                  variant="outlined"
                  :to="productLink"
                >
                  Volver a comprar
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <VCard
            v-if="hasOffer"
            class="purchase-card offer-card"
          >
            <VCardTitle>Oferta de precio</VCardTitle>
            <VCardText>
              <div class="offer-card__header">
                <div class="offer-card__amount">
                  {{ offerAmountLabel || totalPriceLabel }}
                </div>
                <VChip
                  v-if="offerStatusInfo"
                  :color="offerStatusInfo.color"
                  size="small"
                  label
                  class="offer-card__status"
                >
                  {{ offerStatusInfo.label }}
                </VChip>
              </div>
              <p
                v-if="offerStatusInfo && offerStatusInfo.description"
                class="offer-card__description"
              >
                {{ offerStatusInfo.description }}
              </p>
              <div class="offer-card__meta">
                <div
                  v-if="offerDateLabel"
                  class="offer-card__meta-item"
                >
                  <span class="offer-card__meta-label">Enviada</span>
                  <span class="offer-card__meta-value">{{ offerDateLabel }}</span>
                </div>
                <div
                  v-if="offerAcceptedLabel"
                  class="offer-card__meta-item"
                >
                  <span class="offer-card__meta-label">Aceptada</span>
                  <span class="offer-card__meta-value">{{ offerAcceptedLabel }}</span>
                </div>
                <div
                  v-if="offerConfirmedLabel"
                  class="offer-card__meta-item"
                >
                  <span class="offer-card__meta-label">Confirmada</span>
                  <span class="offer-card__meta-value">{{ offerConfirmedLabel }}</span>
                </div>
                <div
                  v-if="offerExpiresLabel && !offerConfirmedLabel"
                  class="offer-card__meta-item"
                >
                  <span class="offer-card__meta-label">Vence</span>
                  <span class="offer-card__meta-value">{{ offerExpiresLabel }}</span>
                </div>
              </div>
              <div
                v-if="offerJustification"
                class="offer-card__justification"
              >
                <span class="offer-card__justification-label">Mensaje enviado</span>
                <p class="offer-card__justification-text">
                  {{ offerJustification }}
                </p>
              </div>
            </VCardText>
          </VCard>

          <VCard class="purchase-card opinion-card">
            <VCardTitle>Que te parecio tu producto?</VCardTitle>
            <VCardText>
              <VAlert
                v-if="opinionSuccess"
                type="success"
                variant="tonal"
                class="mb-4"
              >
                Gracias por compartir tu opinion.
              </VAlert>
              <VAlert
                v-if="opinionError"
                type="error"
                variant="tonal"
                class="mb-4"
              >
                {{ opinionError }}
              </VAlert>
              <VRating
                v-model="opinion.rating"
                class="mb-4"
                color="warning"
                size="32"
              />
              <VTextarea
                v-model="opinion.comment"
                label="Comparte tu experiencia"
                rows="3"
                auto-grow
                hide-details="auto"
              />
              <VBtn
                color="error"
                class="mt-4"
                :loading="isSubmittingOpinion"
                :disabled="isSubmittingOpinion || (!opinion.rating && !opinion.comment)"
                @click="sendOpinion"
              >
                Enviar
              </VBtn>
            </VCardText>
          </VCard>

          <VCard class="purchase-card help-card">
            <VCardTitle>Ayuda con la compra</VCardTitle>
            <VList density="comfortable">
              <VListItem
                v-for="item in helpItems"
                :key="item.id"
                class="help-item"
                rounded="lg"
              >
                <VListItemTitle>{{ item.title }}</VListItemTitle>
                <template #append>
                  <VIcon
                    icon="mdi-chevron-right"
                    size="18"
                  />
                </template>
              </VListItem>
            </VList>
          </VCard>

          <VCard class="purchase-card messages-card">
            <VCardTitle>Mensajes con el vendedor</VCardTitle>
            <VList density="comfortable">
              <VListItem
                class="messages-item"
                rounded="lg"
                :to="messagesRoute"
                link
              >
                <template #prepend>
                  <VAvatar
                    v-if="sellerAvatar"
                    :image="sellerAvatar"
                    size="40"
                    class="mr-3"
                  />
                  <VAvatar
                    v-else
                    size="40"
                    color="primary"
                    class="mr-3"
                  >
                    <span class="avatar-initials">{{ sellerInitials }}</span>
                  </VAvatar>
                </template>
                <VListItemTitle>{{ sellerName }}</VListItemTitle>
                <VListItemSubtitle>Ir al chat de la compra</VListItemSubtitle>
                <template #append>
                  <VIcon
                    icon="mdi-chevron-right"
                    size="18"
                  />
                </template>
              </VListItem>
            </VList>
          </VCard>
        </div>
      </VCol>

      <VCol
        cols="12"
        md="4"
        lg="3"
      >
        <VCard class="purchase-card summary-panel">
          <VCardTitle>Detalle de la compra</VCardTitle>
          <VCardText>
            <div class="summary-panel__date">
              {{ purchase.fecha_compra }}
            </div>
            <div
              v-if="purchase.uuid"
              class="summary-panel__id"
            >
              # {{ purchase.uuid }}
            </div>
            <div
              v-if="statusSummaryLabel"
              class="summary-panel__row"
            >
              <span>Estado</span>
              <span>{{ statusSummaryLabel }}</span>
            </div>
            <div
              v-if="purchaseModeLabel"
              class="summary-panel__row"
            >
              <span>Tipo de compra</span>
              <span>{{ purchaseModeLabel }}</span>
            </div>
            <div class="summary-panel__row summary-panel__row--stack">
              <span class="summary-panel__label">Producto</span>
              <span class="summary-panel__value">{{ productTitle }}</span>
            </div>
            <div
              v-if="productTypeLabel"
              class="summary-panel__row summary-panel__row--stack"
            >
              <span class="summary-panel__label">Tipo</span>
              <span class="summary-panel__value">{{ productTypeLabel }}</span>
            </div>
            <div class="summary-panel__row">
              <span>Cantidad</span>
              <span>{{ quantityLabel }}</span>
            </div>
            <div
              v-if="unitPriceValue !== null"
              class="summary-panel__row"
            >
              <span>Precio unitario</span>
              <span>{{ unitPriceLabel }}</span>
            </div>
            <div
              v-if="publishedPriceLabel"
              class="summary-panel__row"
            >
              <span>Precio publicado</span>
              <span>{{ publishedPriceLabel }}</span>
            </div>
            <div
              v-if="hasOffer && offerAmountLabel"
              class="summary-panel__row"
            >
              <span>Oferta aceptada</span>
              <span>{{ offerAmountLabel }}</span>
            </div>
            <div class="summary-panel__row summary-panel__row--total">
              <span>Total pagado</span>
              <span>{{ totalPriceLabel }}</span>
            </div>

            <VExpansionPanels
              class="summary-panel__details"
              variant="accordion"
            >
              <VExpansionPanel title="Detalles de pago y entrega">
                <VExpansionPanelText>
                  <p class="summary-panel__details-text">
                    Coordina directamente con el vendedor la fecha y el lugar de entrega.
                  </p>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>

  <VContainer
    v-else
    class="purchase-page__empty"
  >
    <VCard class="pa-8 text-center mx-auto empty-card">
      <VCardTitle class="justify-center">
        {{ purchaseError || 'No encontramos la compra' }}
      </VCardTitle>
      <VCardText>
        Revisa el enlace o vuelve a la tienda para continuar navegando.
      </VCardText>
      <VCardActions class="justify-center">
        <VBtn
          color="primary"
          :to="{ name: 'store' }"
        >
          Ir a la tienda
        </VBtn>
      </VCardActions>
    </VCard>
  </VContainer>
</template>

<style scoped lang="scss">
.purchase-page {
  padding: 32px 0 48px;
  min-height: 100%;
}

.purchase-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 16px;
  margin-bottom: 24px;
  font-size: 0.875rem;
  color: #6c7592;

  &__link {
    color: inherit;
    text-decoration: none;
    font-weight: 500;

    &:hover {
      text-decoration: underline;
    }
  }

  &__current {
    color: #1f2233;
    font-weight: 600;
  }
}

.purchase-layout {
  margin: 0;
}

.purchase-main {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.purchase-card {
  border-radius: 14px;
  box-shadow: 0 12px 30px rgba(36, 50, 87, 0.08);
  border: 1px solid #e3e7ef;
}

.summary-card {
  .summary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .summary-info {
    flex: 1;
  }

  .summary-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2233;
    margin-bottom: 4px;
  }

  .summary-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #6c7592;
  }

  .summary-link {
    color: rgb(var(--v-theme-primary));
    text-decoration: none;
    font-weight: 600;

    &:hover {
      text-decoration: underline;
    }
  }

  .summary-title__link {
    color: inherit;
    text-decoration: none;
  }

  .summary-title__link:hover {
    text-decoration: underline;
  }

  .summary-location {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 0.875rem;
    color: #6c7592;
  }

  .summary-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .summary-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    background: #eef1f6;
    color: #515b75;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  .summary-tag--highlight {
    background: rgba(var(--v-theme-primary), 0.16);
    color: rgb(var(--v-theme-primary));
  }

  .summary-price {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 16px;
  }

  .summary-price__label {
    font-size: 0.75rem;
    color: #6c7592;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
  }

  .summary-price__value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2233;
  }

  .summary-price__note {
    display: block;
    margin-top: 2px;
    font-size: 0.8rem;
    color: #6c7592;
  }

  .summary-avatar {
    background: #f0f2f8;
    color: #9aa0b5;

    &--placeholder {
      font-size: 0;
    }
  }
}

.status-card {
  padding-bottom: 4px;

  .status-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 16px;

    &--success {
      background: #e6f7f0;
      color: #137750;
    }

    &--warning {
      background: #fff4db;
      color: #b76b00;
    }

    &--info {
      background: #e4f0ff;
      color: #1c4fb8;
    }

    &--error {
      background: #ffe1e1;
      color: #c42626;
    }
  }

  .status-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2233;
    margin-bottom: 8px;
  }

  .status-description {
    margin: 0;
    color: #515b75;
    font-size: 0.95rem;
  }

  .status-description--muted {
    margin-top: 8px;
    color: #6c7592;
  }

  .status-actions {
    margin-top: 20px;
  }
}

.seller-card {
  .seller-card__header {
    display: flex;
    align-items: center;
  }

  .seller-card__identity {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .seller-card__name {
    font-weight: 600;
    font-size: 1rem;
    color: #1f2233;
  }

  .seller-card__company {
    font-size: 0.875rem;
    color: #515b75;
  }

  .seller-card__location {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: #6c7592;
  }

  .seller-card__metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
    font-size: 0.875rem;
    color: #1f2233;
  }

  .seller-card__metrics-item {
    display: inline-flex;
    align-items: center;
  }

  .seller-card__contact {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
    font-size: 0.875rem;
  }

  .seller-card__contact-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #1f2233;
    text-decoration: none;
  }

  .seller-card__contact-item:hover {
    text-decoration: underline;
  }

  .seller-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
  }

  .avatar-initials {
    font-weight: 600;
    color: #fff;
  }
}

.offer-card {
  .offer-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .offer-card__amount {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2233;
  }

  .offer-card__status {
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
  }

  .offer-card__description {
    margin: 12px 0 0;
    color: #515b75;
    font-size: 0.9rem;
  }

  .offer-card__meta {
    display: grid;
    gap: 12px;
    margin-top: 16px;
  }

  @media (min-width: 600px) {
    .offer-card__meta {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  .offer-card__meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .offer-card__meta-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c7592;
    letter-spacing: 0.05em;
    font-weight: 600;
  }

  .offer-card__meta-value {
    font-size: 0.95rem;
    color: #1f2233;
  }

  .offer-card__justification {
    margin-top: 20px;
    padding: 16px;
    border-radius: 12px;
    background: #f5f7fb;
  }

  .offer-card__justification-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c7592;
    letter-spacing: 0.05em;
    font-weight: 600;
  }

  .offer-card__justification-text {
    margin: 8px 0 0;
    color: #1f2233;
    font-size: 0.95rem;
  }
}

.opinion-card {
  .v-card-title {
    font-weight: 600;
  }

  .v-rating__wrapper {
    justify-content: flex-start;
  }
}

.help-card {
  .help-item {
    border-top: 1px solid #eef1f6;
    cursor: pointer;

    &:first-of-type {
      border-top: none;
    }
  }
}

.messages-card {
  .messages-item {
    border-top: 1px solid #eef1f6;
    cursor: pointer;
  }

  .avatar-initials {
    font-weight: 600;
    color: #fff;
  }
}

.summary-panel {
  position: sticky;
  top: 80px;
  background: #fdfdfd;

  .summary-panel__date {
    font-size: 0.875rem;
    color: #6c7592;
  }

  .summary-panel__id {
    font-size: 0.875rem;
    color: #6c7592;
    margin-bottom: 16px;
  }

  .summary-panel__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 0.95rem;
    color: #1f2233;
  }

  .summary-panel__row--stack {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .summary-panel__label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c7592;
    letter-spacing: 0.05em;
    font-weight: 600;
  }

  .summary-panel__value {
    font-size: 0.95rem;
    color: #1f2233;
    word-break: break-word;
  }

  .summary-panel__row--total {
    font-weight: 700;
    font-size: 1.05rem;
  }

  .summary-panel__details {
    margin-top: 16px;
    border-radius: 12px;
    border: 1px solid #e3e7ef;

    .summary-panel__details-text {
      margin: 0;
      color: #515b75;
      font-size: 0.9rem;
    }
  }
}

.purchase-page__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 160px);
}

.purchase-page__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 160px);
}

.purchase-page__loading-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 160px;
  width: 100%;
}

.empty-card {
  max-width: 420px;
}

@media (max-width: 960px) {
  .purchase-page {
    padding: 24px 0 36px;
  }

  .purchase-breadcrumbs {
    margin-bottom: 16px;
  }

  .summary-panel {
    position: static;
  }
}
</style>
