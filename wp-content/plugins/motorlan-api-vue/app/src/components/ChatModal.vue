<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePurchaseChat } from '@/composables/usePurchaseChat'
import { formatCurrency } from '@/utils/formatCurrency'

interface ChatModalProps {
  purchaseUuid: string
  contextType: 'sale' | 'purchase'
  isOpen: boolean
  publicationTitle?: string
  publicationImage?: string
}

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

const props = defineProps<ChatModalProps>()
const emit = defineEmits<{
  'update:isOpen': [value: boolean]
  close: []
}>()

const chat = usePurchaseChat(props.purchaseUuid)
const purchase = chat.purchase
const viewerRole = chat.viewerRole

const isBuyer = computed(() => viewerRole.value === 'buyer')
const isSeller = computed(() => viewerRole.value === 'seller')

const messagesContainer = ref<HTMLElement | null>(null)
const messageText = ref('')

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

  return parts.slice(0, 2).map((part: string) => part[0].toUpperCase()).join('') || 'U'
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

const productTitle = computed(() => props.publicationTitle || purchase.value?.motor?.title || purchase.value?.title || 'Producto')
const productImage = computed(() => {
  if (props.publicationImage)
    return props.publicationImage

  const image = purchase.value?.motor?.imagen_destacada

  if (!image)
    return null

  if (typeof image === 'string')
    return image

  if (Array.isArray(image))
    return (image[0] && typeof image[0] === 'object' && 'url' in image[0]) ? (image[0] as any).url || null : null

  return (image as any).url || null
})

const formattedPrice = computed(() => formatCurrency(purchase.value?.motor?.acf?.precio_de_venta))
const priceLabel = computed(() => formattedPrice.value || 'Consultar precio')

const otherPartyName = computed(() => {
  if (isBuyer.value)
    return purchase.value?.motor?.author?.name || 'Vendedor'

  return purchase.value?.comprador?.name || 'Comprador'
})

const otherPartyAvatar = computed(() => {
  const avatar = isBuyer.value
    ? purchase.value?.motor?.author?.acf?.avatar
    : purchase.value?.comprador?.avatar

  if (!avatar)
    return null

  if (typeof avatar === 'string')
    return avatar

  return (avatar as any).url || null
})

const otherPartyInitials = computed(() => getInitials(otherPartyName.value))

const statusInfo = computed(() => {
  const status = String(purchase.value?.estado || '').toLowerCase()

  const map: Record<string, { label: string; color: 'success' | 'warning' | 'info' | 'error' }> = {
    entregado: { label: 'Entregado', color: 'success' },
    enviado: { label: 'Enviado', color: 'info' },
    en_proceso: { label: 'En proceso', color: 'info' },
    pendiente: { label: 'Pendiente', color: 'warning' },
    cancelado: { label: 'Cancelado', color: 'error' },
  }

  return map[status] ?? { label: 'En progreso', color: 'info' }
})

const isRealtimeLive = computed(() => chat.isPollingActive.value && !chat.isLocked.value)

const scrollMessagesToBottom = () => {
  if (!messagesContainer.value)
    return

  messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
}

const handleClose = () => {
  emit('update:isOpen', false)
  emit('close')
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
  },
)

watch(
  () => props.isOpen,
  async (isOpen) => {
    if (isOpen) {
      await chat.fetchPurchaseDetails()
      await chat.fetchMessages({ reset: true })
      chat.setupPolling()
      await nextTick(scrollMessagesToBottom)
    }
    else {
      chat.stopPolling()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  chat.stopPolling()
})
</script>

<template>
  <VDialog
    :model-value="isOpen"
    max-width="600px"
    transition="dialog-bottom-transition"
    @update:model-value="handleClose"
  >
    <VCard class="chat-modal rounded-xl overflow-hidden elevation-10">
      <!-- Header -->
      <VCardTitle class="d-flex justify-space-between align-center py-3 px-4 bg-surface elevation-0 border-b">
        <span class="text-h6 font-weight-bold text-high-emphasis">
          {{ isBuyer ? 'Chat con el vendedor' : 'Chat con el comprador' }}
        </span>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="medium-emphasis"
          density="comfortable"
          @click="handleClose"
        />
      </VCardTitle>

      <!-- Context: Product Info -->
      <div class="product-info bg-surface-container-low px-4 py-3 border-b d-flex align-center gap-4">
        <VAvatar
          v-if="productImage"
          :image="productImage"
          size="48"
          class="rounded-lg elevation-2"
        />
        <div class="product-details flex-grow-1 min-w-0">
          <p class="text-subtitle-2 font-weight-bold text-truncate mb-0 text-high-emphasis">
            {{ productTitle }}
          </p>
          <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
            <VIcon
              icon="tabler-building-store"
              size="14"
            />
            <span class="text-truncate">
              {{ otherPartyName }}
            </span>
          </div>
        </div>
      </div>

      <!-- Security Alert -->
      <div class="bg-primary-lighten-5 px-4 py-2 d-flex align-center gap-3 text-caption text-primary">
        <VIcon
          icon="tabler-shield-lock"
          size="16"
          color="primary"
        />
        <span>Por tu seguridad, no compartas datos de contacto directo.</span>
      </div>

      <!-- Chat Body -->
      <VCardText class="pa-0 chat-modal__body bg-background position-relative">
        <div
          ref="messagesContainer"
          class="messages-container px-4 py-4"
        >
          <!-- Loader -->
          <div
            v-if="chat.isFetchingMessages.value && !chat.hasLoadedMessages.value"
            class="d-flex flex-column align-center justify-center h-100 gap-2 text-medium-emphasis"
          >
            <VProgressCircular
              color="primary"
              indeterminate
              size="40"
              width="3"
            />
            <span class="text-caption">Cargando conversación...</span>
          </div>

          <!-- Error -->
          <VAlert
            v-else-if="chat.loadError.value"
            type="error"
            variant="tonal"
            class="ma-4"
            density="compact"
          >
            <div class="d-flex justify-space-between align-center w-100">
              <span class="text-caption">{{ chat.loadError.value }}</span>
              <VBtn
                size="x-small"
                variant="text"
                color="error"
                class="ms-2"
                @click="handleRetryMessages"
              >
                Reintentar
              </VBtn>
            </div>
          </VAlert>

          <!-- Empty State -->
          <div
            v-else-if="!groupedMessages.length"
            class="d-flex flex-column align-center justify-center h-100 text-medium-emphasis gap-3"
          >
            <div class="bg-surface rounded-circle pa-4 elevation-1">
              <VIcon
                icon="tabler-message-2"
                size="32"
                color="primary"
              />
            </div>
            <div class="text-center">
              <p class="text-body-2 font-weight-medium mb-1 text-high-emphasis">
                Aún no hay mensajes.
              </p>
              <p class="text-caption">
                <span v-if="isBuyer">Inicia la conversación con el vendedor.</span>
                <span v-else>Inicia la conversación con el comprador.</span>
              </p>
            </div>
          </div>

          <!-- Messages -->
          <template v-else>
            <div
              v-for="group in groupedMessages"
              :key="group.key"
              class="message-group mb-6"
            >
              <div class="d-flex justify-center mb-4">
                <VChip
                  size="x-small"
                  variant="flat"
                  color="surface-variant"
                  class="font-weight-medium text-caption"
                >
                  {{ group.label }}
                </VChip>
              </div>

              <div
                v-for="message in group.items"
                :key="message.id"
                class="chat-message d-flex mb-3"
                :class="message.is_current_user ? 'justify-end' : 'justify-start'"
              >
                <!-- Avatar (Other) -->
                <VAvatar
                  v-if="!message.is_current_user"
                  size="32"
                  class="me-2 align-self-end mb-1 elevation-1"
                >
                  <VImg
                    v-if="message.avatar"
                    :src="message.avatar"
                  />
                  <span
                    v-else
                    class="text-caption font-weight-bold text-primary"
                  >{{ getInitials(message.display_name || otherPartyName) }}</span>
                </VAvatar>

                <!-- Message Bubble -->
                <div
                  class="message-bubble elevation-1 px-4 py-2"
                  :class="[
                    message.is_current_user
                      ? 'bg-primary text-white rounded-t-xl rounded-bs-xl'
                      : 'bg-surface text-high-emphasis rounded-t-xl rounded-be-xl',
                  ]"
                  style="max-width: 80%;"
                >
                  <p class="text-body-2 mb-1" style="white-space: pre-wrap;">
                    {{ message.message }}
                  </p>
                  <div
                    class="text-caption d-flex align-center justify-end gap-1"
                    :class="message.is_current_user ? 'text-primary-lighten-4' : 'text-medium-emphasis'"
                    style="font-size: 0.65rem;"
                  >
                    {{ formatMessageTime(message.created_at) }}
                    <VIcon
                      v-if="message.is_current_user"
                      icon="tabler-check"
                      size="12"
                    />
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </VCardText>

      <!-- Composer -->
      <VCardActions class="composer bg-surface py-3 px-4 border-t">
        <VAlert
          v-if="chat.sendError.value"
          type="error"
          variant="tonal"
          class="mb-3 w-100"
          closable
          density="compact"
          @click:close="chat.sendError.value = null"
        >
          {{ chat.sendError.value }}
        </VAlert>

        <div class="d-flex align-end w-100 gap-2">
          <VTextarea
            v-model="messageText"
            :disabled="chat.isLocked.value"
            auto-grow
            hide-details
            placeholder="Escribe un mensaje..."
            rows="1"
            max-rows="4"
            density="comfortable"
            variant="outlined"
            bg-color="surface"
            class="composer-input rounded-xl"
            maxlength="1000"
            @keydown.enter.exact.prevent="handleComposerSubmit"
          >
            <template #append-inner>
              <VBtn
                icon="tabler-send"
                variant="text"
                color="primary"
                density="compact"
                :disabled="!canSendMessage"
                :loading="chat.isSending.value"
                @click="handleComposerSubmit"
              />
            </template>
          </VTextarea>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.chat-modal {
  display: flex;
  flex-direction: column;
  max-height: 85vh;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.chat-modal__body {
  flex: 1 1 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.messages-container {
  flex: 1 1 auto;
  overflow-y: auto;
  scroll-behavior: smooth;
  min-height: 300px;
  padding-bottom: 80px !important;

  // Custom scrollbar
  &::-webkit-scrollbar {
    width: 6px;
  }
  &::-webkit-scrollbar-thumb {
    background-color: rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 4px;
  }
}

.composer-input {
  :deep(.v-field__outline) {
    --v-field-border-opacity: 0.15;
  }
  :deep(.v-field--focused .v-field__outline) {
    --v-field-border-opacity: 0.4;
  }
}

// Smooth animations for dynamic content
.message-bubble {
  transition: all 0.2s ease;
}
</style>