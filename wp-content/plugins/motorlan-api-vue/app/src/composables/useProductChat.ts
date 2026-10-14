import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { usePolling } from '@/composables/usePolling'
import { getGuestRoomToken, getStoredGuestEmail, setGuestRoomToken } from '@/utils/guest'

interface ProductMessage {
  id: string
  message: string
  created_at: string
  sender_role: string
  user_id: number
  display_name: string
  avatar?: string | null
  is_current_user?: boolean
  guest_email?: string | null
}

interface ChatMeta {
  current_user_id?: number | null
  viewer_role?: string | null
  server_timestamp?: string | null
  room_token?: string | null
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

  const guestEmail = typeof input?.guest_email === 'string' ? input.guest_email : null

  return {
    id,
    message,
    created_at: createdAt,
    sender_role: senderRole,
    user_id: userId,
    display_name: displayName,
    avatar,
    is_current_user: isCurrentUser,
    guest_email: guestEmail,
  }
}

export function useProductChat(
  productId: number,
  opts?: {
    roomKey?: string | null
    viewerName?: string | null
    roomToken?: string | null
  },
) {
  const { t } = useI18n()
  const messages = ref<ProductMessage[]>([])
  const messageStore = new Map<string, ProductMessage>()

  const loadError = ref<string | null>(null)
  const sendError = ref<string | null>(null)
  const isFetchingMessages = ref(false)
  const hasLoadedMessages = ref(false)
  const isSending = ref(false)
  const isPollingActive = ref(false)
  const isLocked = ref(false)
  // Último código HTTP de error al cargar (p. ej. 403 = sin permiso para esta sala)
  const loadErrorStatus = ref<number | null>(null)

  const currentUserId = ref<number | null>(null)
  const viewerRole = ref<string | null>(null)
  const lastServerTimestamp = ref<string | null>(null)

  const polling = ref<ReturnType<typeof usePolling> | null>(null)

  const roomKey = ref<string | null>(opts?.roomKey ?? null)
  const viewerNameRef = ref<string | null>(opts?.viewerName ?? null)
  // Token firmado de la sala de invitado: sin él el servidor ya no deja leer ni escribir
  // en una sala de invitado con mensajes (la clave sola la conoce también el vendedor)
  const roomToken = ref<string | null>(opts?.roomToken ?? getGuestRoomToken(opts?.roomKey ?? null))

  const endpointBase = `/wp-json/motorlan/v1/products/${productId}/messages`
  const endpointQuery = computed(() => {
    const params = new URLSearchParams()
    if (roomKey.value)
      params.set('room_key', roomKey.value)
    if (roomToken.value)
      params.set('room_token', roomToken.value)

    // El email del invitado ya no viaja en la URL de cada poll: el backend dejó de usarlo
    // para el acceso (era una vía de suplantación) y solo acababa en logs del servidor.

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
        is_current_user: (currentId !== null && currentId !== 0)
          ? message.user_id === currentId
          : Boolean(message.is_current_user || (getStoredGuestEmail() && message.guest_email === getStoredGuestEmail())),
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

    if (typeof meta.room_token === 'string' && meta.room_token) {
      roomToken.value = meta.room_token
      setGuestRoomToken(roomKey.value, meta.room_token)
    }

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
        // Backoff sin actividad: de 20 a ~6 peticiones/min por chat abierto e inactivo;
        // vuelve a 3 s en cuanto llega un mensaje
        minInterval: 3000,
        maxInterval: 10000,
        backoffMultiplier: 1.5,
        // 403/404 permanentes: el polling se detiene; mostrarlo en lugar de "congelarse"
        onError: (status: number) => {
          isPollingActive.value = false
          isLocked.value = true
          loadError.value = status === 404
            ? t('chat.errors.not_found', 'No pudimos encontrar este chat.')
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
    loadErrorStatus.value = null

    try {
      const { data, error, statusCode } = await useApi<any>(createUrl(endpointUrl.value)).get().json()

      if (error.value) {
        // El código HTTP está en statusCode (error.value es un Error sin .status)
        const status = Number(statusCode.value ?? 0)
        loadErrorStatus.value = status

        if (status === 403 && data.value?.code !== 'rest_cookie_invalid_nonce') {
          loadError.value = t('chat.errors.forbidden_view', 'No tienes permisos para ver estos mensajes.')
          isLocked.value = true
          stopPolling()
        }
        else if (status === 404) {
          loadError.value = t('chat.errors.not_found', 'No pudimos encontrar este chat.')
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

  const sendMessage = async (rawMessage: string, meta?: { email?: string; name?: string; website?: string }) => {
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

      // Prioritize meta name, then ref, then null
      const vName = meta?.name || viewerNameRef.value
      if (vName)
        payload.viewer_name = vName

      if (meta?.email)
        payload.guest_email = meta.email

      if (roomToken.value)
        payload.room_token = roomToken.value

      // Honeypot del formulario de invitado (vacío para personas)
      if (meta?.website)
        payload.website = meta.website

      const { data, error, statusCode } = await useApi<any>(createUrl(endpointBase)).post(payload).json()

      if (error.value) {
        const status = Number(statusCode.value ?? 0)

        if (status === 403 && data.value?.code !== 'rest_cookie_invalid_nonce') {
          sendError.value = t('chat.errors.forbidden_send', 'No tienes permisos para enviar mensajes en este chat.')
          isLocked.value = true
          stopPolling()
        }
        else {
          // Mostrar el motivo del backend (p. ej. límite de mensajes 429)
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

  const setRoomKey = (value: string | null) => {
    roomKey.value = value && value.trim().length ? value.trim() : null
    roomToken.value = getGuestRoomToken(roomKey.value)
  }

  const setRoomToken = (value: string | null) => {
    roomToken.value = value && value.trim().length ? value.trim() : null
    if (roomToken.value)
      setGuestRoomToken(roomKey.value, roomToken.value)
  }

  const setViewerName = (value: string | null) => {
    viewerNameRef.value = value && value.trim().length ? value.trim() : null
  }

  return {
    messages,
    loadError,
    loadErrorStatus,
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
    setRoomToken,
    setViewerName,
  }
}
