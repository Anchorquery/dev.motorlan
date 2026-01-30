<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useProductChat } from '@/composables/useProductChat'
import type { Publicacion } from '@/interfaces/publicacion'
import { useUserStore } from '@/@core/stores/user'
import { getOrCreateGuestId, getStoredGuestName } from '@/utils/guest'
import { getPrePurchaseRoomKey } from '@/utils/roomKey'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

type BrandTerm = { term_id: number; name: string; slug: string }

const props = defineProps<{
  publicacion: Publicacion
  roomKey?: string | null
}>()

const emit = defineEmits(['close'])

const userStore = useUserStore()
const guestId = ref<string>('')
const initialViewerName = ref<string | null>(null)

// Guest Form State
import { getStoredGuestEmail, setStoredGuestEmail, setStoredGuestName } from '@/utils/guest'
const formName = ref('')
const formEmail = ref('')
const formMessage = ref('')
const formAgreed = ref(false)
const showGuestForm = computed(() => {
  // Show form if:
  // 1. User is not logged in
  // 2. No messages loaded yet (or empty history)
  // 3. We are not currently sending the first message
  if (userStore.isLoggedIn) return false
  if (chat.hasLoadedMessages.value && chat.messages.value.length > 0) return false
  if (chat.isSending.value) return false 
  
  // Optimization: If local storage has name/email, maybe pre-fill but still show form?
  // Or if we have roomKey which implies history... 
  // Let's rely on message history as the source of truth.
  return true
})

const isFormValid = computed(() => {
  return formName.value.trim().length > 0 
    && formEmail.value.trim().length > 0 
    && /.+@.+\..+/.test(formEmail.value)
    && formMessage.value.trim().length > 0
    && formAgreed.value
})

const handleGuestSubmit = async () => {
  if (!isFormValid.value) return

  // Save to storage
  setStoredGuestName(formName.value)
  setStoredGuestEmail(formEmail.value)
  
  // Update chat state
  chat.setViewerName(formName.value)
  
  // Send message
  await chat.sendMessage(formMessage.value, { 
    email: formEmail.value, 
    name: formName.value 
  })
  
  // Clear form (optional, as view will switch)
}

// Brand Data Fetching
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>(
  createUrl('/wp-json/motorlan/v1/marcas'),
  { immediate: false },
).get().json()

const brandsList = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() =>
  Object.fromEntries(brandsList.value.map(brand => [Number(brand.term_id), brand])),
)
const brandBySlug = computed<Record<string, BrandTerm>>(() =>
  Object.fromEntries(brandsList.value.map(brand => [String(brand.slug), brand])),
)

const brandName = computed(() => {
  const marca: any = props.publicacion?.acf?.marca
  if (marca === null || marca === undefined)
    return ''

  if (typeof marca === 'object') {
    const name = marca.name || marca.title || marca.label
    if (name)
      return name
    const id = Number(marca.id ?? marca.term_id ?? 0)
    if (id && brandById.value[id])
      return brandById.value[id].name
  }

  const asNum = Number(marca)
  if (Number.isFinite(asNum) && asNum > 0) {
    if (brandById.value[asNum])
      return brandById.value[asNum].name
    return '' // Loading or not found, return empty to avoid showing ID/dash
  }

  const asStr = String(marca)
  if (brandBySlug.value[asStr])
    return brandBySlug.value[asStr].name

  return asStr && asStr !== 'null' && asStr !== 'undefined' ? asStr : ''
})

const productTitle = computed(() => {
  const parts: string[] = []

  // 1. Tipo de producto (Category/Title)
  if (props.publicacion.title)
    parts.push(props.publicacion.title)

  // 2. Marca
  if (brandName.value && brandName.value !== '-')
    parts.push(brandName.value)

  // 3. Tipo/modelo
  if (props.publicacion.acf?.tipo_o_referencia)
    parts.push(props.publicacion.acf.tipo_o_referencia)

  // 4. Potencia o Par
  const potencia = props.publicacion.acf?.potencia
  const par = props.publicacion.acf?.par_nominal || props.publicacion.acf?.par

  if (potencia)
    parts.push(`${potencia} kW`)
  else if (par)
    parts.push(`${par} Nm`)

  // 5. Velocidad
  if (props.publicacion.acf?.velocidad)
    parts.push(`${props.publicacion.acf.velocidad} rpm`)

  return parts.join(' - ')
})


const viewerId = computed(() => userStore.user?.id ?? guestId.value)
const defaultRoomKey = computed(() => getPrePurchaseRoomKey(props.publicacion.id, viewerId.value))
const providedRoomKey = computed(() => props.roomKey || null)
const roomKey = computed(() => providedRoomKey.value || defaultRoomKey.value)

const chat = useProductChat(props.publicacion.id, {
  roomKey: roomKey.value,
  viewerName: initialViewerName.value,
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
  void fetchBrands()

  if (!userStore.user) {
    guestId.value = getOrCreateGuestId()
    // Pre-fill form from storage if available
    const storedName = getStoredGuestName()
    const storedEmail = getStoredGuestEmail()
    if (storedName) formName.value = storedName
    if (storedEmail) formEmail.value = storedEmail
  } else {
    guestId.value = String(userStore.user.id)
  }

  initialViewerName.value = userStore.user?.display_name ?? getStoredGuestName()
  if (initialViewerName.value)
    chat.setViewerName(initialViewerName.value)

  chat.setRoomKey(roomKey.value)

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
    max-width="600px"
    persistent
    :model-value="true"
    transition="dialog-bottom-transition"
  >
    <VCard class="chat-modal rounded-xl overflow-hidden elevation-10">
      <!-- Header -->
      <VCardTitle class="d-flex justify-space-between align-center py-3 px-4 bg-surface elevation-0 border-b">
        <span class="text-h6 font-weight-bold text-high-emphasis">Chat con el vendedor</span>
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
          v-if="props.publicacion.imagen_destacada"
          :image="typeof props.publicacion.imagen_destacada === 'string' ? props.publicacion.imagen_destacada : (!Array.isArray(props.publicacion.imagen_destacada) && props.publicacion.imagen_destacada !== null ? props.publicacion.imagen_destacada.url : '')"
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
                {{ (props.publicacion.author.first_name || props.publicacion.author.last_name) 
                    ? `${props.publicacion.author.first_name || ''} ${props.publicacion.author.last_name || ''}`.trim()
                    : 'Vendedor'
                }}
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

      <!-- Guest Form (replaces Chat Body) -->
      <VCardText v-if="showGuestForm" class="pa-6 bg-background flex-grow-1 overflow-y-auto">
        <div class="text-center mb-6">
          <VIcon icon="tabler-mail-fast" size="48" color="primary" class="mb-3" />
          <h3 class="text-h6 font-weight-bold mb-1">Consulta sobre este motor</h3>
          <p class="text-body-2 text-medium-emphasis">
            Completa tus datos para contactar al vendedor. Recibirás una copia en tu email.
          </p>
        </div>

        <VForm @submit.prevent="handleGuestSubmit">
          <AppTextField
            v-model="formName"
            label="Tu nombre *"
            placeholder="Ej. Juan Pérez"
            class="mb-4"
          />
          <AppTextField
            v-model="formEmail"
            label="Tu e-mail *"
            placeholder="ejemplo@correo.com"
            type="email"
            class="mb-4"
          />
          <AppTextarea
            v-model="formMessage"
            label="Mensaje *"
            placeholder="Hola, estoy interesado en este motor..."
            rows="4"
            class="mb-4"
          />

          <div class="d-flex align-start mb-6">
            <VCheckbox
              v-model="formAgreed"
              color="primary"
              density="compact"
              hide-details
              class="mt-0 pt-0"
            >
              <template #label>
                <div class="text-caption text-medium-emphasis ml-2">
                  Acepto las <a href="#" class="text-primary text-decoration-none">condiciones de uso</a> y <a href="#" class="text-primary text-decoration-none">políticas de privacidad</a>.
                </div>
              </template>
            </VCheckbox>
          </div>

          <VBtn
            block
            color="primary"
            size="large"
            type="submit"
            :disabled="!isFormValid"
            :loading="isSendingMessage"
          >
            CONTACTAR AHORA
          </VBtn>
        </VForm>
      </VCardText>

      <!-- Chat Body -->
      <VCardText v-else class="pa-0 chat-modal__body bg-background position-relative">
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

          <!-- Alert for Guests -->
          <VAlert
            v-if="!userStore.isLoggedIn"
            type="info"
            variant="tonal"
            class="mx-0 mb-4 rounded-0 border-b"
            density="compact"
            closable
          >
            <div class="text-caption">
              Estás chateando como <strong>{{ initialViewerName || 'Invitado' }}</strong>. 
              <span class="text-decoration-underline cursor-pointer">Regístrate</span> para guardar tu historial.
            </div>
          </VAlert>

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

          <!-- Empty State (Should rarely show due to form, but good fallback) -->
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
                Chat iniciado
              </p>
              <p class="text-caption">
                Espera la respuesta del vendedor.
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
      
      <!-- Composer (Hidden if form is shown) -->
      <VCardActions v-if="!showGuestForm" class="composer bg-surface py-3 px-4 border-t">
        <div class="d-flex align-end w-100 gap-2">
          <VTextarea
            v-model="messageText"
            :disabled="isConversationLocked"
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
  max-height: 85vh; // Slightly smaller to float nicely
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
