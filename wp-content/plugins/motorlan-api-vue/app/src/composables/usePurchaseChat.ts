import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { usePolling } from '@/composables/usePolling'

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

interface PurchaseMeta {
  current_user_id?: number | null
  viewer_role?: string | null
  server_timestamp?: string | null
}

interface ApplyMessagesOptions {
  replace?: boolean
}

const normalizeTimestamp = (value: string | null | undefined): string => {
  if (!value)
    return new Date().toISOString()

  let normalized = value.trim()

  if (!normalized)
    return new Date().toISOString()

  if (normalized.includes(' '))
    normalized = normalized.replace(' ', 'T')

  if (!/Z$|[+-]\d{2}:\d{2}$/.test(normalized))
    normalized = `${normalized}Z`

  const parsed = Date.parse(normalized)

  if (Number.isNaN(parsed))
    return new Date().toISOString()

  return new Date(parsed).toISOString()
}

const toTimestamp = (value: string): number => {
  if (!value)
    return 0

  const parsed = Date.parse(value)

  return Number.isNaN(parsed) ? 0 : parsed
}

const normalizeMessage = (input: any, fallbackIndex = 0): PurchaseMessage => {
  const id = String(input?.id ?? `msg-${fallbackIndex}`)
  const message = String(input?.message ?? '')
  const createdAt = typeof input?.created_at === 'string' ? normalizeTimestamp(input.created_at) : new Date().toISOString()
  const senderRole = typeof input?.sender_role === 'string' ? input.sender_role : 'buyer'
  const userId = Number.isFinite(Number(input?.user_id)) ? Number(input.user_id) : 0
  const displayName = typeof input?.display_name === 'string' ? input.display_name : ''
  const avatarValue = input?.avatar
  const avatar = typeof avatarValue === 'string' && avatarValue.length ? avatarValue : null
  const isCurrentUser = Boolean(input?.is_current_user ?? false)

  return {
    id,
    message,
    created_at: createdAt,
    sender_role: senderRole,
    user_id: userId,
    display_name: displayName,
    avatar,
    is_current_user: isCurrentUser,
  }
}

export function usePurchaseChat(uuid: string) {
  const { t } = useI18n()
  const purchaseData = ref<any>(null)
  const purchaseError = ref<any>(null)

  const purchase = computed(() => purchaseData.value?.data ?? null)

  const messages = ref<PurchaseMessage[]>([])
  const messageStore = new Map<string, PurchaseMessage>()

  const loadError = ref<string | null>(null)
  const sendError = ref<string | null>(null)
  const isFetchingMessages = ref(false)
  const hasLoadedMessages = ref(false)
  const isSending = ref(false)
  const isPollingActive = ref(false)
  const isLocked = ref(false)

  const currentUserId = ref<number | null>(null)
  const viewerRole = ref<string | null>(null)
  const lastServerTimestamp = ref<string | null>(null)

  const polling = ref<ReturnType<typeof usePolling> | null>(null)

  const endpointUrl = `/wp-json/motorlan/v1/purchases/${uuid}/messages`

  const refreshMessages = () => {
    const currentId = currentUserId.value
    const ordered = Array.from(messageStore.values())
      .map(message => ({
        ...message,
        is_current_user: currentId !== null ? message.user_id === currentId : Boolean(message.is_current_user),
      }))
      .sort((a, b) => toTimestamp(a.created_at) - toTimestamp(b.created_at))

    messages.value = ordered
  }

  const applyMessages = (items: any[], options: ApplyMessagesOptions = {}) => {
    const { replace = false } = options

    if (replace)
      messageStore.clear()

    if (!Array.isArray(items) || !items.length) {
      refreshMessages()
      return
    }

    items.forEach((item, index) => {
      const normalized = normalizeMessage(item, index)
      messageStore.set(normalized.id, normalized)
    })

    refreshMessages()
  }

  const handleMeta = (meta: PurchaseMeta | null | undefined) => {
    if (!meta)
      return

    if (typeof meta.current_user_id === 'number')
      currentUserId.value = meta.current_user_id

    if (typeof meta.viewer_role === 'string')
      viewerRole.value = meta.viewer_role

    if (typeof meta.server_timestamp === 'string' && meta.server_timestamp)
      lastServerTimestamp.value = meta.server_timestamp

    refreshMessages()

    if (polling.value && lastServerTimestamp.value)
      polling.value.sync(lastServerTimestamp.value)
  }

  const stopPolling = () => {
    if (!polling.value)
      return

    polling.value.stop()
    isPollingActive.value = false
  }

  const ensurePolling = () => {
    if (!polling.value) {
      polling.value = usePolling(endpointUrl, (incoming, meta) => {
        if (meta)
          handleMeta(meta)

        if (Array.isArray(incoming) && incoming.length)
          applyMessages(incoming, { replace: false })
      }, {
        // Backoff sin actividad (3 s → 10 s); vuelve a 3 s al llegar un mensaje
        minInterval: 3000,
        maxInterval: 10000,
        backoffMultiplier: 1.5,
        // 403/404 permanentes: el polling se detiene; mostrarlo en lugar de "congelarse"
        onError: (status: number) => {
          isPollingActive.value = false
          isLocked.value = true
          loadError.value = status === 404
            ? t('chat.errors.purchase_not_found', 'No pudimos encontrar esta compra.')
            : t('chat.errors.forbidden_view', 'No tienes permisos para ver estos mensajes.')
        },
      })
    }

    return polling.value
  }

  const setupPolling = () => {
    if (isLocked.value)
      return

    const instance = ensurePolling()

    if (lastServerTimestamp.value)
      instance?.sync(lastServerTimestamp.value)

    if (!isPollingActive.value) {
      instance?.start()
      isPollingActive.value = true
    }
  }

  const fetchPurchaseDetails = async () => {
    const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`)).get().json()
    purchaseData.value = data.value
    purchaseError.value = error.value
  }

  const fetchMessages = async (options: { reset?: boolean } = {}) => {
    if (isFetchingMessages.value)
      return

    const { reset = false } = options

    if (reset) {
      messageStore.clear()
      messages.value = []
      lastServerTimestamp.value = null
      if (polling.value)
        polling.value.sync(null)
    }

    isFetchingMessages.value = true
    loadError.value = null

    try {
      const { data, error, statusCode } = await useApi<any>(createUrl(endpointUrl)).get().json()

      if (error.value) {
        // El código HTTP está en statusCode (error.value es un Error sin .status)
        const status = Number(statusCode.value ?? 0)

        if (status === 403 && data.value?.code !== 'rest_cookie_invalid_nonce') {
          loadError.value = t('chat.errors.forbidden_view', 'No tienes permisos para ver estos mensajes.')
          isLocked.value = true
          stopPolling()
        }
        else if (status === 404) {
          loadError.value = t('chat.errors.purchase_not_found', 'No pudimos encontrar esta compra.')
          isLocked.value = true
          stopPolling()
        }
        else {
          loadError.value = t('chat.errors.load', 'No se pudieron cargar los mensajes.')
          isLocked.value = false
        }

        return
      }

      const payload = Array.isArray(data.value?.data) ? data.value.data : []

      applyMessages(payload, { replace: true })
      handleMeta(data.value?.meta ?? null)
      isLocked.value = false
    }
    finally {
      hasLoadedMessages.value = true
      isFetchingMessages.value = false
    }
  }

  const sendMessage = async (rawMessage: string) => {
    if (isSending.value || isLocked.value)
      return

    const trimmed = rawMessage.trim()

    if (!trimmed)
      return

    isSending.value = true
    sendError.value = null

    try {
      const { data, error, statusCode } = await useApi<any>(createUrl(endpointUrl)).post({ message: trimmed }).json()

      if (error.value) {
        const status = Number(statusCode.value ?? 0)
        const isNonce = data.value?.code === 'rest_cookie_invalid_nonce'

        if (status === 403 && !isNonce) {
          sendError.value = t('chat.errors.forbidden_send_purchase', 'No tienes permisos para enviar mensajes en esta compra.')
          isLocked.value = true
          stopPolling()
        }
        else {
          // Motivo real del backend (mensaje demasiado largo, límite 429, sesión renovada...)
          sendError.value = (data.value && typeof data.value === 'object' && data.value.message) || t('chat.errors.send', 'No se pudo enviar el mensaje.')
        }

        return
      }

      if (data.value?.data)
        applyMessages([data.value.data], { replace: false })

      // No mover el cursor del polling con la respuesta del POST: los mensajes del otro
      // participante llegados entre el último poll y este envío se perderían.
      const { server_timestamp: _ignoredCursor, ...sendMeta } = (data.value?.meta ?? {}) as Record<string, any>
      handleMeta(sendMeta as any)
      isLocked.value = false
    }
    finally {
      isSending.value = false
    }
  }

  return {
    purchase,
    purchaseData,
    purchaseError,
    messages,
    loadError,
    sendError,
    isFetchingMessages,
    hasLoadedMessages,
    isSending,
    isPollingActive,
    isLocked,
    currentUserId,
    viewerRole,
    lastServerTimestamp,
    fetchPurchaseDetails,
    fetchMessages,
    sendMessage,
    setupPolling,
    stopPolling,
  }
}