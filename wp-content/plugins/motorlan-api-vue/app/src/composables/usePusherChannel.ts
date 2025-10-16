import { computed, onBeforeUnmount, ref, unref } from 'vue'
import Pusher, { type Channel } from 'pusher-js'

// Declaración global para evitar error de TypeScript al usar __APP_ENV__
declare const __APP_ENV__: Record<string, any>

type MaybeRef<T> = T | import('vue').Ref<T>
type EventHandler = (payload: any) => void

const parseEnvNumber = (value: unknown): number | undefined => {
  if (value === null || value === undefined || value === '')
    return undefined

  const parsed = Number(value)

  return Number.isNaN(parsed) ? undefined : parsed
}

const getEnv = (key: string): unknown => {
  const appEnv = (typeof __APP_ENV__ !== 'undefined') ? (__APP_ENV__ as Record<string, any>) : undefined

  if (appEnv && key in appEnv)
    return appEnv[key]

  if (typeof process !== 'undefined' && process.env && key in process.env)
    return process.env[key]

  return undefined
}

export interface UsePusherChannelOptions {
  key?: string
  cluster?: string
  authEndpoint?: string
  authHeaders?: Record<string, string> | (() => Record<string, string>)
  forceTLS?: boolean
  disableStats?: boolean
  clientOptions?: Partial<import('pusher-js').Options>
  autoConnect?: boolean
  enabled?: MaybeRef<boolean>
  onClientError?: (error: any) => void
  onSubscriptionSucceeded?: (channel: Channel) => void
  onSubscriptionError?: (error: any) => void
}

export const usePusherChannel = (channelName: MaybeRef<string>, options?: UsePusherChannelOptions) => {
  const client = ref<import('pusher-js').default | null>(null)
  const channel = ref<Channel | null>(null)
  const isConnected = ref(false)
  const error = ref<string | null>(null)

  const resolvedChannelName = computed(() => unref(channelName))

  const eventHandlers = new Map<string, Set<EventHandler>>()

  const isConfigured = computed(() => {
    const key = options?.key ?? getEnv('VITE_PUSHER_APP_KEY')

    return Boolean(key)
  })

  const applyBindings = (target?: Channel | null) => {
    const current = target ?? channel.value

    if (!current)
      return

    eventHandlers.forEach((handlers, eventName) => {
      handlers.forEach(handler => {
        current.bind(eventName, handler)
      })
    })
  }

  const wireChannel = (target: Channel) => {
    target.bind('pusher:subscription_succeeded', () => {
      isConnected.value = true
      error.value = null
      applyBindings(target)
      options?.onSubscriptionSucceeded?.(target)
    })

    target.bind('pusher:subscription_error', (payload: { error?: string; data?: { message?: string } } | string) => {
      isConnected.value = false
      const message = typeof payload === 'string'
        ? payload
        : payload?.error ?? payload?.data?.message ?? 'No pudimos iniciar la conexión en tiempo real.'

      error.value = message
      options?.onSubscriptionError?.(payload)
    })
  }

  const unbindAllFromChannel = () => {
    if (!channel.value)
      return

    eventHandlers.forEach((handlers, eventName) => {
      handlers.forEach(handler => channel.value?.unbind(eventName, handler))
    })
  }

  const connect = (): boolean => {
    if (options?.enabled !== undefined && !unref(options.enabled))
      return false

    const pusherKey = options?.key ?? String(getEnv('VITE_PUSHER_APP_KEY') ?? '')

    if (!pusherKey) {
      error.value = 'La mensajería en tiempo real no está configurada.'
      return false
    }

    const channelId = resolvedChannelName.value

    if (!channelId) {
      error.value = 'No se configuró el canal de Pusher.'
      return false
    }

    if (client.value)
      return true

    error.value = null

    const cluster = options?.cluster ?? String(getEnv('VITE_PUSHER_APP_CLUSTER') ?? '')
    const forceTLSDefault = String(getEnv('VITE_PUSHER_FORCE_TLS') ?? '1') !== '0'
    const forceTLS = options?.forceTLS ?? forceTLSDefault
    const baseUrl = String(getEnv('VITE_API_BASE_URL') ?? '').replace(/\/$/, '')
    const authEndpointFromEnv = String(getEnv('VITE_PUSHER_AUTH_ENDPOINT') ?? '')
    const authEndpoint = options?.authEndpoint
      ?? (authEndpointFromEnv || (baseUrl ? `${baseUrl}/broadcasting/auth` : '/broadcasting/auth'))

    const baseOptions: import('pusher-js').Options = {
      cluster,
      forceTLS,
      disableStats: options?.disableStats ?? true,
      authEndpoint,
      auth: {
        headers: typeof options?.authHeaders === 'function'
          ? options.authHeaders()
          : (options?.authHeaders ?? {}),
      },
    }

    const host = String(getEnv('VITE_PUSHER_HOST') ?? '')
    const wsPort = parseEnvNumber(getEnv('VITE_PUSHER_PORT'))
    const wssPort = parseEnvNumber(getEnv('VITE_PUSHER_WSS_PORT'))

    if (host) {
      baseOptions.wsHost = host

      if (typeof wsPort === 'number')
        baseOptions.wsPort = wsPort

      if (typeof wssPort === 'number')
        baseOptions.wssPort = wssPort

      baseOptions.enabledTransports = forceTLS ? ['wss'] : ['ws', 'wss']
    }

    if (!forceTLS)
      baseOptions.forceTLS = false

    if (options?.clientOptions) {
      const overrides = { ...options.clientOptions }

      Object.assign(baseOptions, overrides)

      if (overrides.auth) {
        baseOptions.auth = {
          ...(baseOptions.auth ?? {}),
          ...overrides.auth,
          headers: {
            ...(baseOptions.auth?.headers ?? {}),
            ...(overrides.auth.headers ?? {}),
          },
        }
      }
    }

    client.value = new Pusher(pusherKey, baseOptions)

    client.value.bind('error', (event: any) => {
      if (event?.data?.message)
        error.value = event.data.message

      options?.onClientError?.(event)
    })

    channel.value = client.value.subscribe(channelId)
    wireChannel(channel.value)

    return true
  }

  const bind = (eventName: string, handler: EventHandler) => {
    if (!eventHandlers.has(eventName))
      eventHandlers.set(eventName, new Set())

    eventHandlers.get(eventName)!.add(handler)

    if (channel.value)
      channel.value.bind(eventName, handler)

    return () => {
      unbind(eventName, handler)
    }
  }

  const unbind = (eventName: string, handler?: EventHandler) => {
    const handlers = eventHandlers.get(eventName)

    if (!handlers)
      return

    if (handler) {
      if (channel.value)
        channel.value.unbind(eventName, handler)

      handlers.delete(handler)

      if (!handlers.size)
        eventHandlers.delete(eventName)
    }
    else {
      if (channel.value)
        channel.value.unbind(eventName)

      handlers.clear()
      eventHandlers.delete(eventName)
    }
  }

  const disconnect = () => {
    unbindAllFromChannel()

    const channelId = resolvedChannelName.value

    if (channel.value && client.value) {
      channel.value.unbind_all()
      client.value.unsubscribe(channelId)
    }

    if (client.value) {
      client.value.unbind_all()
      client.value.disconnect()
    }

    channel.value = null
    client.value = null
    isConnected.value = false
  }

  onBeforeUnmount(() => {
    disconnect()
  })

  if (options?.autoConnect) {
    if (typeof queueMicrotask === 'function')
      queueMicrotask(() => { connect() })
    else
      setTimeout(() => { connect() }, 0)
  }

  return {
    connect,
    disconnect,
    bind,
    unbind,
    client,
    channel,
    isConnected,
    isConfigured,
    error,
  }
}

export type UsePusherChannelReturn = ReturnType<typeof usePusherChannel>
