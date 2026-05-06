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
const viewerRole = chat.viewerRole

const isBuyer = computed(() => viewerRole.value === 'buyer')
const isSeller = computed(() => viewerRole.value === 'seller')
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

const breadcrumbs = computed(() => {
  // Si es vendedor, no mostrar el enlace a "Detalle de la compra" (solo el título)
  if (isSeller.value) {
    return [
      { title: 'Compras', to: '/dashboard/purchases' },
      { title: 'Mensajes de la compra', disabled: true },
    ]
  }
  // Si es comprador, mostrar todo
  return [
    { title: 'Compras', to: '/dashboard/purchases' },
    { title: 'Detalle de la compra', to: `/dashboard/purchases/${uuid}` },
    { title: 'Mensajes de la compra', disabled: true },
  ]
})

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

  return parts.slice(0, 2).map((part: string) => part.toUpperCase()).join('') || 'V'
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

  return parts.slice(0, 2).map((part: string) => part.toUpperCase()).join('') || 'U'
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
                  Aún no hay mensajes.
                  <span v-if="isBuyer">Inicia la conversación con el vendedor.</span>
                  <span v-else>Inicia la conversación con el comprador.</span>
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
                    :label="isBuyer ? 'Escríbele al vendedor' : 'Escríbele al comprador'"
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
                  <!-- Solo el comprador puede ver el enlace al producto -->
                  <RouterLink
                    v-if="productLink && isBuyer"
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
  background: var(--v-theme-background-light, #f8f9fa);
}

.chat-panel {
  display: flex;
  flex-direction: column;
  height: 600px;
  overflow: hidden;

  .chat-panel__header {
    background: white;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
  }

  .chat-panel__seller-name {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--v-theme-on-surface);
  }

  .chat-panel__seller-meta {
    margin: 0;
    font-size: 0.85rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }

  .chat-panel__status {
    margin-top: 2px;
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), 0.4);
    display: flex;
    align-items: center;
    gap: 4px;

    &::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ccc;
      display: inline-block;
      margin-top: 4px;
    }
  }

  .chat-panel__status--live {
    color: var(--v-theme-success);
    font-weight: 600;

    &::before {
      background: var(--v-theme-success);
      box-shadow: 0 0 0 2px rgba(var(--v-theme-success), 0.2);
    }
  }

  .chat-panel__messages {
    flex: 1;
    padding: 1.5rem;
    background: var(--v-theme-background-light, #f8f9fa);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;

    /* Custom scrollbar */
    &::-webkit-scrollbar {
      width: 6px;
    }
    &::-webkit-scrollbar-thumb {
      background: rgba(var(--v-border-color), 0.1);
      border-radius: 10px;
    }
  }

  .chat-panel__date-chip {
    align-self: center;
    background: white;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.5);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 1;
  }

  .chat-message {
    display: flex;
    max-width: 85%;
    gap: 12px;

    &.chat-message--self {
      align-self: flex-end;
      flex-direction: row-reverse;

      .chat-message__bubble {
        background: var(--v-theme-primary);
        color: white;
        border-radius: 18px 18px 4px 18px;
        box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2);
      }

      .chat-message__time {
        color: rgba(255, 255, 255, 0.7);
        text-align: right;
      }

      .chat-message__text {
        color: white;
      }
    }

    &.chat-message--other {
      align-self: flex-start;

      .chat-message__bubble {
        background: white;
        border-radius: 18px 18px 18px 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      }
    }

    &__bubble {
      padding: 12px 16px;
      position: relative;
    }

    &__text {
      margin: 0;
      font-size: 0.95rem;
      line-height: 1.5;
      white-space: pre-line;
      color: var(--v-theme-on-surface);
    }

    &__time {
      display: block;
      font-size: 0.7rem;
      margin-top: 4px;
      font-weight: 500;
      color: rgba(var(--v-theme-on-surface), 0.4);
    }
  }

  .chat-panel__composer {
    background: white;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid rgba(var(--v-border-color), 0.08);

    .chat-panel__composer-actions {
      display: flex;
      align-items: flex-end;
      gap: 1rem;
    }
  }
}

.summary-card {
  .summary-card__status-header {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 1.5rem;
  }

  .summary-card__chip {
    display: inline-flex;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    width: fit-content;
  }

  .summary-card__chip--success { background: rgba(var(--v-theme-success), 0.1); color: var(--v-theme-success); }
  .summary-card__chip--info    { background: rgba(var(--v-theme-info), 0.1);    color: var(--v-theme-info); }
  .summary-card__chip--warning { background: rgba(var(--v-theme-warning), 0.1); color: var(--v-theme-warning); }
  .summary-card__chip--error   { background: rgba(var(--v-theme-error), 0.1);   color: var(--v-theme-error); }

  .summary-card__product {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(var(--v-border-color), 0.03);
    border-radius: 12px;
    align-items: center;
  }

  .summary-card__product-title {
    font-weight: 700;
    color: var(--v-theme-on-surface);
    text-decoration: none;
    line-height: 1.2;
    display: block;
    margin-bottom: 4px;

    &:hover { color: var(--v-theme-primary); }
  }

  .summary-card__product-meta {
    font-size: 0.85rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin: 0;
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
