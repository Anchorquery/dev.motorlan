<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useProductChat } from '@/composables/useProductChat'
import type { Publicacion } from '@/interfaces/publicacion'

const props = defineProps<{
  publicacion: Publicacion
}>()

const emit = defineEmits(['close'])

const chat = useProductChat(props.publicacion.id)

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
  },
)

watch(
  () => chat.isLocked.value,
  value => {
    if (value)
      chat.stopPolling()
    else
      chat.setupPolling()
  },
)

onMounted(async () => {
  await chat.fetchMessages({ reset: true })
  chat.setupPolling()
  await nextTick(scrollMessagesToBottom)
})

onBeforeUnmount(() => {
  chat.stopPolling()
})
</script>

<template>
  <VDialog
    max-width="500px"
    persistent
    :model-value="true"
  >
    <VCard class="chat-modal">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>Escribe un mensaje</span>
        <VBtn
          icon="mdi-close"
          variant="text"
          @click="emit('close')"
        />
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-0">
        <div class="product-info">
          <VAvatar
            v-if="props.publicacion.imagen_destacada"
            :image="props.publicacion.imagen_destacada"
            size="50"
            class="mr-4"
          />
          <div class="product-details">
            <p class="product-title">
              {{ props.publicacion.title }}
            </p>
            <p class="product-seller">
              Vendido por: {{ props.publicacion.author.name }}
            </p>
          </div>
          <div class="product-price">
            {{ props.publicacion.acf.precio_de_venta }}€
          </div>
        </div>
        <div class="security-alert">
          <VIcon
            icon="mdi-lock"
            class="mr-2"
          />
          <span>
            No compartas información personal como email o número de teléfono.
          </span>
        </div>
        <div
          ref="messagesContainer"
          class="messages-container"
        >
          <div
            v-if="isLoadingMessages && !hasLoadedMessages"
            class="loader"
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
            class="ma-4"
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
            class="empty-chat"
          >
            <VIcon
              icon="tabler-message-circle"
              size="36"
              class="mb-2"
              color="primary"
            />
            <p>
              Aún no hay mensajes.
            </p>
          </div>
          <template v-else>
            <div
              v-for="group in groupedMessages"
              :key="group.key"
              class="message-group"
            >
              <div class="date-chip">
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
                    {{ getInitials(message.display_name) }}
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
      </VCardText>
      <VCardActions class="composer">
        <VTextarea
          v-model="messageText"
          :disabled="isConversationLocked"
          auto-grow
          hide-details
          label="Escribe un mensaje..."
          rows="1"
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
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.chat-modal {
  .product-info {
    display: flex;
    align-items: center;
    padding: 16px;
    background: #f5f7fb;
  }

  .product-details {
    flex-grow: 1;
  }

  .product-title {
    font-weight: 600;
  }

  .product-seller {
    font-size: 0.875rem;
    color: #6c7592;
  }

  .product-price {
    font-weight: bold;
    color: #1f2233;
  }

  .security-alert {
    padding: 12px 16px;
    background: #e6ecfa;
    color: #4b5675;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
  }

  .messages-container {
    height: 400px;
    overflow-y: auto;
    padding: 16px;
  }

  .loader,
  .empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c7592;
  }

  .message-group {
    margin-bottom: 16px;
  }

  .date-chip {
    text-align: center;
    margin-bottom: 12px;
    font-size: 0.75rem;
    font-weight: 600;
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

  .composer {
    border-top: 1px solid #e3e7ef;
    padding: 16px;
  }
}
</style>
