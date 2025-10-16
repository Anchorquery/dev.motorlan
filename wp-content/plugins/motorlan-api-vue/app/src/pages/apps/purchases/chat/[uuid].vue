<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useCookie } from '@/@core/composable/useCookie'
import { useApi } from '@/composables/useApi'
import { usePusherChannel } from '@/composables/usePusherChannel'
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

const accessToken = useCookie<string | null>('accessToken')

const API_BASE_URL = (process.env.VITE_API_BASE_URL || '').toString().replace(/\/$/, '')
const PUSHER_AUTH_ENDPOINT = API_BASE_URL
  ? `${API_BASE_URL}/wp-json/motorlan/v1/purchases/pusher/auth`
  : '/wp-json/motorlan/v1/purchases/pusher/auth'
const PUSHER_CHANNEL_NAME = `private-purchase-${uuid}`

const purchaseData = ref<any>(null)
const purchaseError = ref<any>(null)

const purchase = computed(() => purchaseData.value?.data ?? null)
const purchaseLoadError = computed(() => purchaseError.value?.message ?? null)

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

  return parts.slice(0, 2).map((part: string) => part?.toUpperCase()).join('') || 'V'
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
      title: 'La compra se canceló',
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

const messages = ref<PurchaseMessage[]>([])
const groupedMessages = computed<MessageGroup[]>(() => {
  if (!messages.value.length)
    return []

  const groups: MessageGroup[] = []
  const buckets = new Map<string, PurchaseMessage[]>()

  for (const message of messages.value) {
    const date = parseMessageDate(message.created_at)
    const key = date.toISOString().split('T')[0]

    if (!buckets.has(key))
      buckets.set(key, [])

    buckets.get(key)!.push(message)
  }

  const sortedKeys = Array.from(buckets.keys()).sort((a, b) => a.localeCompare(b))

  for (const key of sortedKeys) {
    const bucket = buckets.get(key) ?? []

    bucket.sort((a, b) => parseMessageDate(a.created_at).getTime() - parseMessageDate(b.created_at).getTime())

    const firstMessage = bucket[0]
    const label = capitalize(dateFormatter.format(parseMessageDate(firstMessage?.created_at)))

    groups.push({
      key,
      label,
      items: bucket,
    })
  }

  return groups
})

const messagesError = ref<string | null>(null)
const sendError = ref<string | null>(null)
const isLoadingMessages = ref(false)
const hasLoadedMessages = ref(false)
const isFetchingMessages = ref(false)
const isSendingMessage = ref(false)
const isConversationLocked = ref(false)
const messageText = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

const canSendMessage = computed(() => !isConversationLocked.value && messageText.value.trim().length > 0 && !isSendingMessage.value)

const realtimeStatusLabel = computed(() => {
  if (isConversationLocked.value)
    return 'La mensajería está bloqueada.'

  if (isRealtimeConnected.value)
    return 'Conectado en tiempo real.'

  if (isRealtimeConfigured.value)
    return 'Conectando en tiempo real...'

  return 'Actualizamos los mensajes cada 10 segundos.'
})

const timeFormatter = new Intl.DateTimeFormat('es-VE', { hour: '2-digit', minute: '2-digit' })
const dateFormatter = new Intl.DateTimeFormat('es-VE', { day: 'numeric', month: 'long' })

const capitalize = (value: string | undefined): string => {
  if (!value)
    return ''

  return value.charAt(0).toUpperCase() + value.slice(1)
}

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)

  return parts.slice(0, 2).map((part: string) => part?.toUpperCase()).join('') || 'U'
}

const parseMessageDate = (input: string | null | undefined): Date => {
  if (!input)
    return new Date()

  let normalized = input.trim()

  if (!normalized)
    return new Date()

  if (normalized.includes(' ')) {
    normalized = normalized.replace(' ', 'T')
  }

  if (!/Z$|[+-]\d{2}:\d{2}$/.test(normalized))
    normalized = `${normalized}Z`

  const parsed = new Date(normalized)

  if (Number.isNaN(parsed.getTime()))
    return new Date()

  return parsed
}

const formatMessageTime = (value: string) => timeFormatter.format(parseMessageDate(value))

const extractStatus = (err: any): number | null => {
  if (!err)
    return null

  if (typeof err.status === 'number')
    return err.status

  if (err.data && typeof err.data.status === 'number')
    return err.data.status

  return null
}

const extractErrorMessage = (err: any, fallback: string): string => {
  if (!err)
    return fallback

  if (typeof err === 'string')
    return err

  if (err.data && typeof err.data.message === 'string')
    return err.data.message

  if (typeof err.message === 'string')
    return err.message

  return fallback
}

const normalizeMessage = (item: any, fallbackIndex = 0): PurchaseMessage => ({
  id: String(item?.id ?? `msg-${fallbackIndex}`),
  message: String(item?.message ?? ''),
  created_at: typeof item?.created_at === 'string' ? item.created_at : '',
  sender_role: typeof item?.sender_role === 'string' ? item.sender_role : 'buyer',
  user_id: Number(item?.user_id ?? 0),
  display_name: typeof item?.display_name === 'string' ? item.display_name : '',
  avatar: item?.avatar ?? null,
  is_current_user: Boolean(item?.is_current_user ?? false),
})

const scrollMessagesToBottom = () => {
  if (messagesContainer.value)
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
}

watch(messages, () => {
  nextTick(() => {
    scrollMessagesToBottom()
  })
})

let pollTimer: ReturnType<typeof setInterval> | null = null
let unbindRealtimeHandler: (() => void) | null = null

const buildAuthHeaders = () => {
  const headers: Record<string, string> = {}

  if (accessToken.value)
    headers.Authorization = `Bearer ${accessToken.value}`

  const nonce = (window as any)?.wpData?.nonce
  if (nonce)
    headers['X-WP-Nonce'] = nonce

  return headers
}

const startPolling = (interval = 10000) => {
  if (pollTimer || isConversationLocked.value)
    return

  pollTimer = setInterval(() => {
    void fetchMessages()
  }, interval)
}

const stopPolling = () => {
  if (!pollTimer)
    return

  clearInterval(pollTimer)
  pollTimer = null
}

const {
  connect: connectRealtime,
  disconnect: disconnectRealtime,
  bind: bindRealtimeEvent,
  isConfigured: isRealtimeConfigured,
  isConnected: isRealtimeConnected,
  error: realtimeError,
} = usePusherChannel(PUSHER_CHANNEL_NAME, {
  authEndpoint: PUSHER_AUTH_ENDPOINT,
  authHeaders: buildAuthHeaders,
  onSubscriptionSucceeded: () => {
    stopPolling()
  },
  onSubscriptionError: () => {
    realtimeError.value = 'No se pudo establecer la conexion en tiempo real. Actualizaremos automaticamente.'
    disconnectRealtime()
    if (!isConversationLocked.value)
      startPolling()
  },
  onClientError: () => {
    if (!isConversationLocked.value && !isRealtimeConnected.value)
      startPolling()
  },
})

const teardownRealtime = () => {
  if (unbindRealtimeHandler) {
    unbindRealtimeHandler()
    unbindRealtimeHandler = null
  }

  disconnectRealtime()
}

let realtimeRetryTimer: ReturnType<typeof setTimeout> | null = null

const handleRealtimePayload = () => {
  if (isFetchingMessages.value) {
    if (realtimeRetryTimer)
      return

    realtimeRetryTimer = setTimeout(() => {
      realtimeRetryTimer = null
      void fetchMessages()
    }, 500)

    return
  }

  void fetchMessages()
}

const setupRealtime = (): boolean => {
  if (isConversationLocked.value)
    return false

  if (!isRealtimeConfigured.value) {
    if (!realtimeError.value)
      realtimeError.value = 'Mensajeria en tiempo real no esta configurada. Actualizando cada 10 segundos.'

    return false
  }

  realtimeError.value = null

  const connected = connectRealtime()

  if (!connected) {
    if (!realtimeError.value)
      realtimeError.value = 'No pudimos iniciar la conexion en tiempo real.'

    return false
  }

  if (!unbindRealtimeHandler)
    unbindRealtimeHandler = bindRealtimeEvent('purchase.message', handleRealtimePayload)

  return true
}

const ensureRealtimeOrPolling = () => {
  if (isConversationLocked.value)
    return

  const realtimeReady = setupRealtime()

  if (!realtimeReady) {
    if (!realtimeError.value)
      realtimeError.value = 'Actualizamos los mensajes cada 10 segundos.'

    startPolling()
  }
}

const fetchMessages = async () => {
  if (isFetchingMessages.value)
    return

  isFetchingMessages.value = true

  if (!hasLoadedMessages.value)
    isLoadingMessages.value = true

  messagesError.value = null

  try {
    const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}/messages`)).get().json()

    if (error.value) {
      const status = extractStatus(error.value)

      if (status === 403) {
        messagesError.value = 'No tienes permisos para ver estos mensajes.'
        isConversationLocked.value = true
        stopPolling()
      }
      else if (status === 404) {
        messagesError.value = 'No pudimos encontrar esta compra.'
        isConversationLocked.value = true
        stopPolling()
      }
      else {
        messagesError.value = extractErrorMessage(error.value, 'No se pudieron cargar los mensajes.')
        isConversationLocked.value = false
      }

      return
    }

    const incoming = Array.isArray(data.value?.data) ? data.value.data : []

    messages.value = incoming
      .map((item: any, index: number) => normalizeMessage(item, index))
      .filter((item: PurchaseMessage) => item.message.trim().length > 0)
      .sort((a: PurchaseMessage, b: PurchaseMessage) => parseMessageDate(a.created_at).getTime() - parseMessageDate(b.created_at).getTime())

    isConversationLocked.value = false
  }
  finally {
    hasLoadedMessages.value = true
    isLoadingMessages.value = false
    isFetchingMessages.value = false
  }

  await nextTick(scrollMessagesToBottom)
}

const sendMessage = async () => {
  const trimmed = messageText.value.trim()

  if (!trimmed || isSendingMessage.value || isConversationLocked.value)
    return

  isSendingMessage.value = true
  sendError.value = null

  const { data: sendResponse, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}/messages`))
    .post({ message: trimmed })
    .json()

  if (error.value) {
    const status = extractStatus(error.value)

    if (status === 403) {
      sendError.value = 'No tienes permisos para enviar mensajes en esta compra.'
      isConversationLocked.value = true
      stopPolling()
    }
    else {
      sendError.value = extractErrorMessage(error.value, 'No se pudo enviar el mensaje.')
    }
  }
  else {
    const payload = sendResponse.value?.data
    if (payload) {
      const normalized = normalizeMessage(payload, messages.value.length)
      normalized.is_current_user = true
      messages.value = [...messages.value, normalized]
        .sort((a, b) => parseMessageDate(a.created_at).getTime() - parseMessageDate(b.created_at).getTime())
    }

    messageText.value = ''
    await nextTick(scrollMessagesToBottom)
    void fetchMessages()
  }

  isSendingMessage.value = false
}

const handleComposerSubmit = async (event?: Event) => {
  event?.preventDefault()

  if (!canSendMessage.value)
    return

  await sendMessage()
}

watch(isConversationLocked, value => {
  if (value) {
    teardownRealtime()
    stopPolling()
  }
  else {
    ensureRealtimeOrPolling()
  }
})

onMounted(async () => {
  ensureRealtimeOrPolling()

  const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`)).get().json()

  purchaseData.value = data.value
  purchaseError.value = error.value
  await fetchMessages()
})

onBeforeUnmount(() => {
  stopPolling()
  teardownRealtime()

  if (realtimeRetryTimer) {
    clearTimeout(realtimeRetryTimer)
    realtimeRetryTimer = null
  }
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
                    :class="{ 'chat-panel__status--live': isRealtimeConnected }"
                  >
                    {{ realtimeStatusLabel }}
                  </p>
                </div>
              </div>
            </div>

            <VAlert
              v-if="realtimeError"
              type="warning"
              variant="tonal"
              density="compact"
              class="chat-panel__notice mx-4 mt-3"
            >
              {{ realtimeError }}
            </VAlert>

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
                {{ messagesError }}
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
                  Aún no hay mensajes. Inicia la conversación con el vendedor.
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
                    label="Escríbele al vendedor"
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
                    {{ quantityLabel }} • {{ priceLabel }}
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
