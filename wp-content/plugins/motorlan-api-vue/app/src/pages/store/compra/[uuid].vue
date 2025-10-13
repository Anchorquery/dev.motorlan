<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const uuid = route.params.uuid as string

const { data } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`)).get().json()
const purchase = computed(() => data.value?.data)

const opinion = ref({ valoracion: 0, comentario: '' })
const isSubmittingOpinion = ref(false)
const opinionSuccess = ref(false)
const opinionError = ref<string | null>(null)

const productTitle = computed(() => purchase.value?.motor?.title || purchase.value?.title || 'Producto')
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

const quantityLabel = computed(() => {
  const raw = (purchase.value as any)?.cantidad ?? (purchase.value as any)?.quantity ?? 1
  const qty = Number(raw) || 1

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

const formattedPrice = computed(() => formatCurrency(purchase.value?.motor?.acf?.precio_de_venta))
const priceLabel = computed(() => formattedPrice.value || 'Consultar precio')

const statusInfo = computed(() => {
  const status = String(purchase.value?.estado || '').toLowerCase()
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

  return map[status] || {
    label: 'En progreso',
    title: 'Estamos procesando tu compra',
    description: withDate('estamos gestionando tu pedido.'),
    tone: 'info',
  }
})

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

  return parts.slice(0, 2).map(part => part[0]?.toUpperCase()).join('') || 'V'
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
    await $api(`/wp-json/motorlan/v1/purchases/${uuid}/opinion`, {
      method: 'POST',
      body: opinion.value,
    })
    opinionSuccess.value = true
    opinion.value = { valoracion: 0, comentario: '' }
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
    v-if="purchase"
    fluid
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
                    {{ productTitle }}
                  </h1>
                  <div class="summary-meta">
                    <span>{{ quantityLabel }}</span>
                    <span v-if="productLink?.params?.slug">|</span>
                    <RouterLink
                      v-if="productLink?.params?.slug"
                      class="summary-link"
                      :to="productLink"
                    >
                      Ver detalle
                    </RouterLink>
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
                v-model="opinion.valoracion"
                class="mb-4"
                color="warning"
                size="32"
              />
              <VTextarea
                v-model="opinion.comentario"
                label="Comparte tu experiencia"
                rows="3"
                auto-grow
                hide-details="auto"
              />
              <VBtn
                color="error"
                class="mt-4"
                :loading="isSubmittingOpinion"
                :disabled="isSubmittingOpinion || (!opinion.valoracion && !opinion.comentario)"
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
              <VListItem class="messages-item" rounded="lg">
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
                <VListItemSubtitle>Ver mensajes</VListItemSubtitle>
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
            <div class="summary-panel__row">
              <span>Producto</span>
              <span>{{ priceLabel }}</span>
            </div>
            <div class="summary-panel__row summary-panel__row--total">
              <span>Total</span>
              <span>{{ priceLabel }}</span>
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
    fluid
    class="purchase-page__empty"
  >
    <VCard class="pa-8 text-center mx-auto empty-card">
      <VCardTitle class="justify-center">
        No encontramos la compra
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
  background: #f5f7fb;
  padding: 32px 0 48px;
  min-height: calc(100vh - 120px);
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

  .status-actions {
    margin-top: 20px;
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
    padding: 10px 0;
    font-size: 0.95rem;
    color: #1f2233;
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
  background: #f5f7fb;
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
