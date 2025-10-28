import { computed, ref } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { usePolling } from '@/composables/usePolling'

interface ProductMessage {
  id: string
  message: string
  created_at: string
  sender_role: string
  user_id: number
  display_name: string
  avatar?: string | null
  is_current_user?: boolean
}

interface ChatMeta {
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

const normalizeMessage = (input: any, fallbackIndex = 0): ProductMessage => {
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

export function useProductChat(
  productId: number,
  opts?: {
    roomKey?: string | null
    viewerName?: string | null
  },
) {
  const messages = ref<ProductMessage[]>([])
  const messageStore = new Map<string, ProductMessage>()

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

  const roomKey = ref<string | null>(opts?.roomKey ?? null)
  const viewerNameRef = ref<string | null>(opts?.viewerName ?? null)

  const endpointBase = `/wp-json/motorlan/v1/products/${productId}/messages`
  const endpointQuery = computed(() => {
    const params = new URLSearchParams()
    if (roomKey.value)
      params.set('room_key', roomKey.value)

    return params.toString()
  })
  const endpointUrl = computed(() => {
    const qs = endpointQuery.value
    return qs ? `${endpointBase}?${qs}` : endpointBase
  })

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

  const handleMeta = (meta: ChatMeta | null | undefined) => {
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
      polling.value = usePolling(endpointUrl.value, (incoming, meta) => {
        if (meta)
          handleMeta(meta)

        if (Array.isArray(incoming) && incoming.length)
          applyMessages(incoming, { replace: false })
      })
    }

    return polling.value
  }

  const setupPolling = () => {
    if (isLocked.value)
      return

    const instance = ensurePolling()

    if (lastServerTimestamp.value)
      instance.sync(lastServerTimestamp.value)

    if (!isPollingActive.value) {
      instance.start()
      isPollingActive.value = true
    }
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
      const { data, error } = await useApi<any>(createUrl(endpointUrl.value)).get().json()

      if (error.value) {
        const status = Number(error.value?.status ?? error.value?.data?.status ?? 0)

        if (status === 403) {
          loadError.value = 'No tienes permisos para ver estos mensajes.'
          isLocked.value = true
          stopPolling()
        }
        else if (status === 404) {
          loadError.value = 'No pudimos encontrar este chat.'
          isLocked.value = true
          stopPolling()
        }
        else {
          loadError.value = 'No se pudieron cargar los mensajes.'
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
      const payload: Record<string, any> = { message: trimmed }
      if (roomKey.value)
        payload.room_key = roomKey.value
      if (viewerNameRef.value)
        payload.viewer_name = viewerNameRef.value

      const { data, error } = await useApi<any>(createUrl(endpointBase)).post(payload).json()

      if (error.value) {
        const status = Number(error.value?.status ?? error.value?.data?.status ?? 0)

        if (status === 403) {
          sendError.value = 'No tienes permisos para enviar mensajes en este chat.'
          isLocked.value = true
          stopPolling()
        }
        else {
          sendError.value = 'No se pudo enviar el mensaje.'
        }

        return
      }

      if (data.value?.data)
        applyMessages([data.value.data], { replace: false })

      handleMeta(data.value?.meta ?? null)
      isLocked.value = false
    }
    finally {
      isSending.value = false
    }
  }

  const setRoomKey = (value: string | null) => {
    roomKey.value = value && value.trim().length ? value.trim() : null
  }

  const setViewerName = (value: string | null) => {
    viewerNameRef.value = value && value.trim().length ? value.trim() : null
  }

  return {
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
    fetchMessages,
    sendMessage,
    setupPolling,
    stopPolling,
    setRoomKey,
    setViewerName,
  }
}
