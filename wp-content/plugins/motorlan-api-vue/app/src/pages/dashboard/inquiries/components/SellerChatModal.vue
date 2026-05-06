<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useProductChat } from '@/composables/useProductChat'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{ productId: number; roomKey: string; productTitle?: string | null }>()
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
  <VDialog max-width="700px" persistent :model-value="true">
    <VCard class="seller-chat-modal motor-card-enhanced overflow-hidden">
      <VCardTitle class="pa-0">
        <div class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-message-2" color="primary" />
            <span class="text-h6 font-weight-bold text-premium-title">{{ productTitle || 'Consultas sobre el producto' }}</span>
          </div>
          <IconBtn @click="emit('close')">
            <VIcon icon="tabler-x" size="20" />
          </IconBtn>
        </div>
      </VCardTitle>
      
      <VCardText class="pa-0">
        <div ref="messagesContainer" class="messages-container bg-light">
          <div v-if="isLoadingMessages && !hasLoadedMessages" class="loader">
            <VProgressCircular color="primary" indeterminate size="40" width="4" />
          </div>
          <VAlert v-else-if="messagesError" type="error" variant="tonal" class="ma-4 rounded-lg">
            <div class="d-flex justify-space-between align-center gap-4">
              <span>{{ messagesError }}</span>
              <VBtn size="small" variant="elevated" color="error" class="rounded-pill" @click="handleRetryMessages">Reintentar</VBtn>
            </div>
          </VAlert>
          <div v-else-if="!groupedMessages.length" class="empty-chat">
            <div class="pa-6 rounded-circle bg-white shadow-sm mb-4">
              <VIcon icon="tabler-message-chatbot" size="48" color="primary" />
            </div>
            <p class="text-h6 font-weight-medium">Sin mensajes previos</p>
            <p class="text-body-2 text-muted">Inicia la conversación respondiendo al interesado.</p>
          </div>
          <template v-else>
            <div v-for="group in groupedMessages" :key="group.key" class="message-group">
              <div class="date-divider">
                <span class="date-divider__text">{{ group.label }}</span>
              </div>
              <div v-for="message in group.items" :key="message.id" class="chat-message" :class="message.is_current_user ? 'chat-message--self' : 'chat-message--other'">
                <div v-if="!message.is_current_user" class="chat-message__avatar">
                  <VAvatar v-if="message.avatar" :image="message.avatar" size="36" class="border shadow-sm" />
                  <VAvatar v-else size="36" color="primary" variant="tonal" class="border shadow-sm font-weight-bold">{{ getInitials(message.display_name) }}</VAvatar>
                </div>
                <div class="chat-message__bubble shadow-sm">
                  <p class="chat-message__text">{{ message.message }}</p>
                  <span class="chat-message__time">{{ formatMessageTime(message.created_at) }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>
      </VCardText>

      <VDivider />
      
      <VCardActions class="composer pa-4 bg-surface">
        <VTextarea 
          v-model="messageText" 
          :disabled="isConversationLocked" 
          auto-grow 
          hide-details 
          placeholder="Escribe tu respuesta aquí..." 
          rows="1" 
          class="flex-grow-1 mr-2" 
          maxlength="1000"
          variant="outlined"
          density="comfortable"
          @keydown.enter.exact.prevent="handleComposerSubmit" 
        />
        <VBtn 
          color="primary" 
          variant="elevated" 
          :disabled="!canSendMessage" 
          :loading="isSendingMessage" 
          icon="tabler-send"
          class="rounded-lg"
          @click="handleComposerSubmit" 
        />
      </VCardActions>
    </VCard>
</VDialog>
</template>

<style scoped lang="scss">
.seller-chat-modal {
  .messages-container { 
    height: 480px; 
    overflow-y: auto; 
    padding: 1.5rem;
    background: var(--v-theme-background-light, #f8f9fa);
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
  
  .loader, .empty-chat { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    height: 100%; 
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  
  .message-group { 
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  
  .date-divider { 
    align-self: center;
    background: white;
    padding: 4px 16px;
    border-radius: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 0.5rem;
    z-index: 1;

    .date-divider__text {
      font-size: 0.75rem;
      font-weight: 700;
      color: rgba(var(--v-theme-on-surface), 0.5);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
  }

  .chat-message { 
    display: flex; 
    gap: 12px; 
    max-width: 85%;
    
    &.chat-message--self { 
      align-self: flex-end;
      flex-direction: row-reverse; 
      
      .chat-message__bubble { 
        background: var(--v-theme-primary);
        color: white;
        border-radius: 18px 18px 4px 18px;
        box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2);
        
        .chat-message__text { color: white; }
        .chat-message__time { color: rgba(255, 255, 255, 0.7); text-align: right; }
      }
    }
  
    &.chat-message--other { 
      align-self: flex-start;
      
      .chat-message__bubble { 
        background: white;
        border-radius: 18px 18px 18px 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        color: var(--v-theme-on-surface);
        
        .chat-message__text { color: var(--v-theme-on-surface); }
        .chat-message__time { color: rgba(var(--v-theme-on-surface), 0.4); }
      }
    }
  }

  .chat-message__bubble { 
    padding: 12px 16px; 
    position: relative;
  }
  
  .chat-message__text { 
    margin: 0; 
    white-space: pre-line; 
    line-height: 1.5; 
    font-size: 0.95rem; 
  }
  
  .chat-message__time { 
    display: block; 
    margin-top: 4px; 
    font-size: 0.7rem; 
    font-weight: 500;
  }
  
  .composer { 
    border-top: 1px solid rgba(var(--v-border-color), 0.08);
    background: white;
  }
}
</style>

