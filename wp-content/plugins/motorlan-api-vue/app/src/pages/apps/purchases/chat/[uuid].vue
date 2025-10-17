<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePurchaseChat } from '@/composables/usePurchaseChat'
import { formatCurrency } from '@/utils/formatCurrency'

interface PurchaseMessage {
  id: string
  message: string
  created_at: string
  sender_role: string
  user_id: number
  display_name: string
  avatar?: string | null
  is_current_user?: boolean
}

interface MessageGroup {
  key: string
  label: string
  items: PurchaseMessage[]
}

const route = useRoute()
const uuid = route.params.uuid as string

const chat = usePurchaseChat(uuid)

const purchase = chat.purchase
const purchaseLoadError = computed(() => {
  const error = chat.purchaseError.value

  if (!error)
    return null

  if (typeof error === 'string')
    return error

  if (error.data && typeof error.data.message === 'string')
    return error.data.message

  if (typeof error.message === 'string')
    return error.message

  return null
})

const breadcrumbs = computed(() => [
  { title: 'Compras', to: '/apps/purchases' },
  { title: 'Detalle de la compra', to: `/apps/purchases/${uuid}` },
  { title: 'Mensajes de la compra', disabled: true },
])

const formattedPrice = computed(() => formatCurrency(purchase.value?.motor?.acf?.precio_de_venta))
const priceLabel = computed(() => formattedPrice.value || 'Consultar precio')

const quantityLabel = computed(() => {
  const raw = (purchase.value as any)?.cantidad ?? (purchase.value as any)?.quantity ?? 1
  const qty = Number(raw) || 1

  return `${qty} ${qty === 1 ? 'unidad' : 'unidades'}`
})

const productTitle = computed(() => purchase.value?.motor?.title || purchase.value?.title || 'Producto')

const productImage = computed(() => {
  const image = purchase.value?.motor?.imagen_destacada

  if (!image)
    return null

  if (typeof image === 'string')
    return image

  if (Array.isArray(image))
    return (image[0] && typeof image[0] === 'object' && 'url' in image[0]) ? (image[0] as any).url || null : null

  return image.url || null
})

const productSlug = computed(() => purchase.value?.motor?.slug || null)
const productLink = computed(() => (productSlug.value ? { name: 'store-slug', params: { slug: productSlug.value } } : null))

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

  return parts.slice(0, 2).map(part => part.toUpperCase()).join('') || 'V'
})

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
      description: withDate('salió a entrega.'),
      tone: 'info',
    },
    en_proceso: {
      label: 'En proceso',
      title: 'Estamos preparando tu compra',
      description: withDate('estamos gestionando el envío.'),
      tone: 'info',
    },
    pendiente: {
      label: 'Pendiente',
      title: 'Tu pago está pendiente',
      description: withDate('registramos tu orden. Te avisaremos cuando avance.'),
      tone: 'warning',
    },
    cancelado: {
      label: 'Cancelado',
      title: 'La compra fue cancelada',
      description: withDate('se detuvo el proceso de compra.'),
      tone: 'error',
    },
  }

  return map[status] ?? {
    label: 'Sin estado',
    title: 'Seguimiento en curso',
    description: withDate('seguimos monitoreando esta compra.'),
    tone: 'info',
  }
})

const messagesContainer = ref<HTMLElement | null>(null)
const messageText = ref('')

const messagesError = chat.loadError
const sendError = chat.sendError
const isSendingMessage = computed(() => chat.isSending.value)
const isConversationLocked = chat.isLocked
const hasLoadedMessages = chat.hasLoadedMessages
const isLoadingMessages = computed(() => chat.isFetchingMessages.value && !chat.hasLoadedMessages.value)

const canSendMessage = computed(() => !chat.isLocked.value && messageText.value.trim().length > 0 && !chat.isSending.value)

const timeFormatter = new Intl.DateTimeFormat('es-VE', { hour: '2-digit', minute: '2-digit' })
const dateFormatter = new Intl.DateTimeFormat('es-VE', { day: 'numeric', month: 'long' })

const capitalize = (value: string | undefined): string => {
  if (!value)
    return ''

  return value.charAt(0).toUpperCase() + value.slice(1)
}

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)

  return parts.slice(0, 2).map(part => part.toUpperCase()).join('') || 'U'
}

const formatMessageTime = (value: string) => timeFormatter.format(new Date(value))

const groupedMessages = computed<MessageGroup[]>(() => {
  const items = chat.messages.value
  if (!items.length)
    return []

  const buckets = new Map<string, PurchaseMessage[]>()

  for (const message of items) {
    const key = message.created_at.slice(0, 10)

    if (!buckets.has(key))
      buckets.set(key, [])

    buckets.get(key)!.push(message)
  }

  return Array.from(buckets.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([key, bucket]) => {
      bucket.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())

      const first = bucket[0]
      const label = capitalize(dateFormatter.format(new Date(first?.created_at ?? Date.now())))

      return {
        key,
        label,
        items: bucket,
      }
    })
})

const realtimeStatusLabel = computed(() => {
  if (chat.isLocked.value)
    return 'La mensajería está bloqueada.'

  if (chat.isPollingActive.value)
    return 'Actualizamos los mensajes cada 3 segundos.'

  return 'Conectando con el chat...'
})

const isRealtimeLive = computed(() => chat.isPollingActive.value && !chat.isLocked.value)

const scrollMessagesToBottom = () => {
  if (!messagesContainer.value)
    return

  messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
}

const handleComposerSubmit = async (event?: Event) => {
  event?.preventDefault()

  if (!canSendMessage.value)
    return

  const previousCount = chat.messages.value.length
  const text = messageText.value

  await chat.sendMessage(text)

  if (!chat.sendError.value) {
    messageText.value = ''
    if (chat.messages.value.length > previousCount)
      await nextTick(scrollMessagesToBottom)
  }
}

const handleRetryMessages = async () => {
  await chat.fetchMessages({ reset: false })
}

watch(
  () => chat.messages.value.length,
  async (length, previous) => {
    if (length === previous)
      return

    await nextTick(scrollMessagesToBottom)
  }
)

watch(
  () => chat.isLocked.value,
  value => {
    if (value)
      chat.stopPolling()
    else
      chat.setupPolling()
  }
)

onMounted(async () => {
  await chat.fetchPurchaseDetails()
  await chat.fetchMessages({ reset: true })
  chat.setupPolling()
  await nextTick(scrollMessagesToBottom)
})

onBeforeUnmount(() => {
  chat.stopPolling()
})
</script>

<template>
  <div class="purchase-messaging-page">
    <VContainer class="py-6">
      <VBreadcrumbs
        :items="breadcrumbs"
        class="purchase-messaging-page__breadcrumbs"
      />

      <VAlert
        v-if="purchaseLoadError"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        {{ purchaseLoadError }}
      </VAlert>

      <VRow
        v-else-if="!purchase"
        class="g-6"
      >
        <VCol cols="12">
          <VCard class="pa-6 d-flex justify-center">
            <VProgressCircular
              color="primary"
              indeterminate
              size="32"
              width="3"
            />
          </VCard>
        </VCol>
      </VRow>

      <VRow
        v-else
        class="g-6"
      >
        <VCol
          cols="12"
          lg="8"
        >
          <VCard class="chat-panel">
            <div class="chat-panel__header">
              <div class="d-flex align-center">
                <VAvatar
                  v-if="sellerAvatar"
                  :image="sellerAvatar"
                  size="46"
                  class="mr-4"
                />
                <VAvatar
                  v-else
                  size="46"
                  color="primary"
                  variant="tonal"
                  class="mr-4"
                >
                  {{ sellerInitials }}
                </VAvatar>
                <div>
                  <p class="chat-panel__seller-name">
                    {{ sellerName }}
                  </p>
                  <p class="chat-panel__seller-meta">
                    Compra #{{ purchase?.uuid }}
                  </p>
                  <p
                    class="chat-panel__status"
                    :class="{ 'chat-panel__status--live': isRealtimeLive }"
                  >
                    {{ realtimeStatusLabel }}
                  </p>
                </div>
              </div>
            </div>

            <VDivider />

            <div
              ref="messagesContainer"
              class="chat-panel__messages"
            >
              <div
                v-if="isLoadingMessages && !hasLoadedMessages"
                class="chat-panel__loader"
              >
                <VProgressCircular
                  color="primary"
                  indeterminate
                  size="32"
                  width="3"
                />
              </div>

              <VAlert
                v-else-if="messagesError"
                type="error"
                variant="tonal"
                class="mb-4"
              >
                <div class="d-flex justify-space-between align-center gap-4">
                  <span>{{ messagesError }}</span>
                  <VBtn
                    size="small"
                    variant="text"
                    color="primary"
                    @click="handleRetryMessages"
                  >
                    Reintentar
                  </VBtn>
                </div>
              </VAlert>

              <div
                v-else-if="!groupedMessages.length"
                class="chat-panel__empty"
              >
                <VIcon
                  icon="tabler-message-circle"
                  size="36"
                  class="mb-2"
                  color="primary"
                />
                <p>
                  AÃºn no hay mensajes. Inicia la conversaciÃ³n con el vendedor.
                </p>
              </div>

              <template v-else>
                <div
                  v-for="group in groupedMessages"
                  :key="group.key"
                  class="chat-panel__group"
                >
                  <div class="chat-panel__date-chip">
                    {{ group.label }}
                  </div>

                  <div
                    v-for="message in group.items"
                    :key="message.id"
                    class="chat-message"
                    :class="message.is_current_user ? 'chat-message--self' : 'chat-message--other'"
                  >
                    <div
                      v-if="!message.is_current_user"
                      class="chat-message__avatar"
                    >
                      <VAvatar
                        v-if="message.avatar"
                        :image="message.avatar"
                        size="36"
                      />
                      <VAvatar
                        v-else
                        size="36"
                        color="secondary"
                        variant="tonal"
                      >
                        {{ getInitials(message.display_name || sellerName) }}
                      </VAvatar>
                    </div>

                    <div class="chat-message__bubble">
                      <p class="chat-message__text">
                        {{ message.message }}
                      </p>
                      <span class="chat-message__time">
                        {{ formatMessageTime(message.created_at) }}
                      </span>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <div class="chat-panel__composer">
              <VAlert
                v-if="sendError"
                type="error"
                variant="tonal"
                class="mb-3"
              >
                {{ sendError }}
              </VAlert>

              <form @submit="handleComposerSubmit">
                <div class="chat-panel__composer-actions">
                  <VTextarea
                    v-model="messageText"
                    :disabled="isConversationLocked"
                    :counter="1000"
                    auto-grow
                    hide-details
                    label="EscrÃ­bele al vendedor"
                    rows="2"
                    class="flex-grow-1"
                    maxlength="1000"
                    @keydown.enter.exact.prevent="handleComposerSubmit"
                  />
                  <VBtn
                    color="primary"
                    variant="flat"
                    :disabled="!canSendMessage"
                    :loading="isSendingMessage"
                    icon="mdi-send"
                    @click="handleComposerSubmit"
                  />
                </div>
              </form>
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard class="summary-card">
            <VCardTitle>
              Compra #{{ purchase?.uuid }}
            </VCardTitle>
            <VCardSubtitle v-if="purchase?.fecha_compra">
              {{ purchase?.fecha_compra }}
            </VCardSubtitle>

            <VCardText>
              <div class="summary-card__status">
                <span
                  class="summary-card__chip"
                  :class="`summary-card__chip--${statusInfo.tone}`"
                >
                  {{ statusInfo.label }}
                </span>
                <p class="summary-card__description">
                  {{ statusInfo.description }}
                </p>
              </div>

              <VDivider class="my-5" />

              <div class="summary-card__product">
                <VAvatar
                  v-if="productImage"
                  :image="productImage"
                  size="64"
                  class="mr-4"
                />
                <VAvatar
                  v-else
                  size="64"
                  color="primary"
                  variant="tonal"
                  class="mr-4"
                >
                  {{ productTitle.charAt(0).toUpperCase() }}
                </VAvatar>

                <div class="summary-card__product-info">
                  <RouterLink
                    v-if="productLink"
                    :to="productLink"
                    class="summary-card__product-title"
                  >
                    {{ productTitle }}
                  </RouterLink>
                  <span
                    v-else
                    class="summary-card__product-title"
                  >
                    {{ productTitle }}
                  </span>

                  <p class="summary-card__product-meta">
                    {{ quantityLabel }} â€¢ {{ priceLabel }}
                  </p>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VContainer>
  </div>
</template>

<style scoped lang="scss">
.purchase-messaging-page {
  min-height: 100vh;
  background: #f5f7fb;
}

.purchase-messaging-page__breadcrumbs {
  margin-bottom: 24px;
}

.chat-panel {
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  overflow: hidden;

  .chat-panel__header {
    padding: 20px 24px;
    background: #fff;
  }

  .chat-panel__seller-name {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: #1f2233;
  }

  .chat-panel__seller-meta {
    margin: 2px 0 0;
    font-size: 0.85rem;
    color: #6c7592;
  }

  .chat-panel__status {
    margin: 4px 0 0;
    font-size: 0.75rem;
    color: #8090b1;
  }

  .chat-panel__status--live {
    color: #1b7a4a;
    font-weight: 600;
  }

  .chat-panel__notice {
    font-size: 0.8rem;
  }

  .chat-panel__messages {
    flex: 1;
    padding: 24px;
    background: #f8f9fc;
    overflow-y: auto;
  }

  .chat-panel__loader {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 240px;
  }

  .chat-panel__empty {
    min-height: 240px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #6c7592;
    font-size: 0.95rem;
    gap: 8px;
  }

  .chat-panel__date-chip {
    align-self: center;
    margin: 12px auto 16px;
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #e6ecfa;
    color: #4b5675;
  }

  .chat-message {
    display: flex;
    align-items: flex-end;
    margin-bottom: 16px;
    gap: 12px;

    &__avatar {
      flex-shrink: 0;
    }

    &__bubble {
      max-width: 70%;
      background: #ffffff;
      border-radius: 16px;
      padding: 12px 16px;
      box-shadow: 0 1px 3px rgba(15, 35, 95, 0.08);
    }

    &__text {
      margin: 0;
      white-space: pre-line;
      color: #1f2233;
    }

    &__time {
      display: block;
      margin-top: 6px;
      font-size: 0.75rem;
      color: #6c7592;
      text-align: right;
    }

    &--self {
      flex-direction: row-reverse;

      .chat-message__bubble {
        background: #d6e4ff;
        color: #1c4fb8;
        border-top-right-radius: 4px;
        border-top-left-radius: 16px;
      }

      .chat-message__time {
        color: #4766c2;
        text-align: left;
      }
    }

    &--other {
      .chat-message__bubble {
        background: #ffffff;
        border-top-left-radius: 4px;
        border-top-right-radius: 16px;
      }
    }
  }

  .chat-panel__composer {
    padding: 16px 24px 24px;
    background: #fff;
    border-top: 1px solid #e3e7ef;
  }

  .chat-panel__composer-actions {
    display: flex;
    align-items: flex-end;
    gap: 12px;
  }
}

.summary-card {
  border-radius: 16px;

  .summary-card__status {
    margin-bottom: 16px;
  }

  .summary-card__chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
  }

  .summary-card__chip--success {
    background: #d9f4e5;
    color: #1b7a4a;
  }

  .summary-card__chip--warning {
    background: #fff0d2;
    color: #a36a05;
  }

  .summary-card__chip--info {
    background: #dce9ff;
    color: #1c4fb8;
  }

  .summary-card__chip--error {
    background: #ffe1e1;
    color: #c42626;
  }

  .summary-card__description {
    margin: 8px 0 0;
    color: #515b75;
    font-size: 0.9rem;
  }

  .summary-card__product {
    display: flex;
    align-items: center;
  }

  .summary-card__product-info {
    display: flex;
    flex-direction: column;
  }

  .summary-card__product-title {
    font-weight: 600;
    font-size: 1rem;
    color: #1f2233;
    text-decoration: none;
  }

  .summary-card__product-title:hover {
    color: #1c4fb8;
  }

  .summary-card__product-meta {
    margin: 6px 0 0;
    font-size: 0.85rem;
    color: #6c7592;
  }
}

@media (max-width: 960px) {
  .purchase-messaging-page {
    padding-top: 16px;
  }

  .chat-panel .chat-panel__messages {
    padding: 16px;
  }

  .summary-card {
    margin-top: 16px;
  }
}
</style>
