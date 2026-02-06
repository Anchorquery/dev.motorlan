<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useProductChat } from '@/composables/useProductChat'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{ productId: number; roomKey: string; productTitle?: string | null; productImage?: string | null }>()
const emit = defineEmits(['close', 'read'])

const chat = useProductChat(props.productId, { roomKey: props.roomKey })

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

const groupedMessages = computed(() => {
  const items = chat.messages.value
  if (!items.length)
    return []
  const buckets = new Map<string, any[]>()
  for (const message of items) {
    const key = message.created_at.slice(0, 10)
    if (!buckets.has(key)) buckets.set(key, [])
    buckets.get(key)!.push(message)
  }
  return Array.from(buckets.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([key, bucket]) => {
      bucket.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
      const first = bucket[0]
      const label = capitalize(dateFormatter.format(new Date(first?.created_at ?? Date.now())))
      return { key, label, items: bucket }
    })
})

const scrollMessagesToBottom = () => {
  if (!messagesContainer.value) return
  messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
}

const markRead = async () => {
  await useApi<any>(createUrl(`/wp-json/motorlan/v1/products/${props.productId}/rooms`)).post({ room_key: props.roomKey }).json()
  emit('read')
}

const handleComposerSubmit = async (event?: Event) => {
  event?.preventDefault()
  if (!canSendMessage.value) return
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
    if (length === previous) return
    await nextTick(scrollMessagesToBottom)
  },
)

watch(
  () => chat.isLocked.value,
  value => {
    if (value) chat.stopPolling()
    else chat.setupPolling()
  },
)

onMounted(async () => {
  await chat.fetchMessages({ reset: true })
  chat.setupPolling()
  await nextTick(scrollMessagesToBottom)
  await markRead()
})

onBeforeUnmount(() => {
  chat.stopPolling()
})
</script>

<template>
  <VDialog
    max-width="600px"
    persistent
    :model-value="true"
    transition="dialog-bottom-transition"
  >
    <VCard class="chat-modal rounded-xl overflow-hidden elevation-10">
      <!-- Header -->
      <VCardTitle class="d-flex justify-space-between align-center py-3 px-4 bg-surface elevation-0 border-b">
        <span class="text-h6 font-weight-bold text-high-emphasis">Responder consulta</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="medium-emphasis"
          density="comfortable"
          @click="emit('close')"
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
        <VAvatar
          v-else
          size="48"
          color="primary"
          variant="tonal"
          class="rounded-lg"
        >
          <VIcon icon="tabler-package" size="24" />
        </VAvatar>
        <div class="product-details flex-grow-1 min-w-0">
          <p class="text-subtitle-2 font-weight-bold text-truncate mb-0 text-high-emphasis">
            {{ productTitle || 'Consulta sobre el producto' }}
          </p>
          <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
            <VIcon icon="tabler-message-circle" size="14" />
            <span>Consulta de comprador interesado</span>
          </div>
        </div>
      </div>

      <!-- Security Alert -->
      <div class="bg-primary-lighten-5 px-4 py-2 d-flex align-center gap-3 text-caption text-primary">
        <VIcon icon="tabler-shield-lock" size="16" color="primary" />
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
            v-if="isLoadingMessages && !hasLoadedMessages"
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
            v-else-if="messagesError"
            type="error"
            variant="tonal"
            class="ma-4"
            density="compact"
          >
            <div class="d-flex justify-space-between align-center w-100">
              <span class="text-caption">{{ messagesError }}</span>
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
              <VIcon icon="tabler-message-2" size="32" color="primary" />
            </div>
            <div class="text-center">
              <p class="text-body-2 font-weight-medium mb-1 text-high-emphasis">
                Sin mensajes previos
              </p>
              <p class="text-caption">
                Inicia la conversación respondiendo al interesado.
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
                  color="primary"
                  variant="tonal"
                >
                  <VImg
                    v-if="message.avatar"
                    :src="message.avatar"
                  />
                  <span
                    v-else
                    class="text-caption font-weight-bold text-primary"
                  >{{ getInitials(message.display_name) }}</span>
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
        <div class="d-flex align-end w-100 gap-2">
          <VTextarea
            v-model="messageText"
            :disabled="isConversationLocked"
            auto-grow
            hide-details
            placeholder="Escribe tu respuesta..."
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
                :loading="isSendingMessage"
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
  min-height: 350px;
  
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

// Ensure smooth animations for dynamic content
.message-bubble {
  transition: all 0.2s ease;
}
</style>
