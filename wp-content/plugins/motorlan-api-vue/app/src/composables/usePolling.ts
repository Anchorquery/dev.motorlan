import { type ComputedRef, type Ref, isRef, ref, unref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

export interface PollingOptions {
  minInterval?: number
  maxInterval?: number
  backoffMultiplier?: number
  /** Error permanente (403/404): el polling se detiene y se avisa al composable */
  onError?: (status: number) => void
}

/**
 * Composable generico para realizar polling contra un endpoint REST.
 * Soporta intervalo adaptativo para reducir carga cuando no hay actividad.
 *
 * @param urlBase - URL base del endpoint (puede ser Ref o Computed).
 * @param onData - Callback que recibe los datos nuevos y la metadata cruda.
 * @param intervalMsOrOptions - Intervalo fijo o objeto de opciones adaptativas.
 */
export function usePolling(
  urlBase: string | Ref<string> | ComputedRef<string>,
  onData: (data: any[], meta: any | null) => void,
  intervalMsOrOptions: number | PollingOptions = 3000
) {
  const options: Required<Omit<PollingOptions, 'onError'>> & Pick<PollingOptions, 'onError'> = typeof intervalMsOrOptions === 'number'
    ? { minInterval: intervalMsOrOptions, maxInterval: intervalMsOrOptions, backoffMultiplier: 1 }
    : { minInterval: 3000, maxInterval: 15000, backoffMultiplier: 1.5, ...intervalMsOrOptions } as Required<Omit<PollingOptions, 'onError'>> & Pick<PollingOptions, 'onError'>

  const isRunning = ref(false)
  const lastTimestamp = ref<string | null>(null)
  const currentInterval = ref(options.minInterval)
  const consecutiveEmptyPolls = ref(0)
  const consecutive429 = ref(0)
  let timer: any | null = null

  const fetchUpdates = async () => {
    try {
      let url = unref(urlBase)
      if (lastTimestamp.value) {
        const params = new URLSearchParams({ since_timestamp: lastTimestamp.value })
        url += (url.includes('?') ? '&' : '?') + params.toString()
      }

      const { data, error, statusCode, response } = await useApi<any>(createUrl(url)).get().json()

      // Rate limiting (429): esperar Retry-After o 60-180 s, pero NUNCA parar del todo
      // (antes, tras 3 respuestas 429 el polling moría en silencio y el chat parecía congelado)
      if (statusCode.value === 429) {
        consecutive429.value++
        const retryAfter = Number(response.value?.headers?.get('Retry-After') ?? 0)
        currentInterval.value = Number.isFinite(retryAfter) && retryAfter > 0
          ? Math.min(retryAfter * 1000 + 1000, 300000)
          : Math.min(60000 * consecutive429.value, 180000)
        return
      }
      consecutive429.value = 0

      // 403/404 permanentes (sala sin permiso, publicación borrada, sesión caducada):
      // parar y avisar en lugar de seguir consultando cada pocos segundos.
      // El 403 por nonce caducado no cuenta: useApi lo renueva y el siguiente poll funciona.
      const status = Number(statusCode.value ?? 0)
      if ((status === 403 && data.value?.code !== 'rest_cookie_invalid_nonce') || status === 404) {
        isRunning.value = false
        if (timer !== null) {
          clearTimeout(timer)
          timer = null
        }
        options.onError?.(status)
        return
      }

      if (!error.value && data.value) {
        const payload = Array.isArray(data.value?.data) ? data.value.data : []
        const meta = data.value?.meta ?? null

        if (payload.length > 0) {
          // Hay datos nuevos: resetear intervalo
          consecutiveEmptyPolls.value = 0
          currentInterval.value = options.minInterval ?? 3000
          onData(payload, meta)
        } else {
          // No hay datos: aumentar intervalo (backoff)
          consecutiveEmptyPolls.value++
          if (meta) onData([], meta) // Aún pasamos meta para sincronizar timestamp

          if (options.backoffMultiplier && options.backoffMultiplier > 1) {
            const nextInterval = currentInterval.value * options.backoffMultiplier
            currentInterval.value = Math.min(nextInterval, options.maxInterval ?? 15000)
          }
        }

        const serverTs = meta?.server_timestamp ?? data.value?.meta?.server_timestamp
        if (serverTs)
          lastTimestamp.value = serverTs
      }
    }
    catch (err) {
      console.error('Polling error:', err)
    }
  }

  let inFlight = false

  const isDocumentHidden = () => typeof document !== 'undefined' && document.visibilityState === 'hidden'

  const poll = () => {
    if (!isRunning.value || inFlight) return
    timer = null

    // RENDIMIENTO: con la pestaña oculta no se consulta (cada poll es un arranque
    // completo de WordPress en el servidor). Se reanuda en visibilitychange.
    if (isDocumentHidden()) return

    inFlight = true

    // Ejecutar fetch
    void fetchUpdates().finally(() => {
      inFlight = false

      // Programar siguiente ejecución solo después de terminar la actual
      // esto previene solapamiento de peticiones lentas
      if (isRunning.value && timer === null) {
        timer = setTimeout(poll, currentInterval.value)
      }
    })
  }

  // Al volver a la pestaña se consulta en el acto en lugar de esperar al siguiente ciclo
  const onVisibilityChange = () => {
    if (!isRunning.value || isDocumentHidden() || inFlight) return

    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
    poll()
  }

  const start = () => {
    if (isRunning.value)
      return

    isRunning.value = true
    currentInterval.value = options.minInterval ?? 3000
    consecutiveEmptyPolls.value = 0

    if (typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', onVisibilityChange)
      document.addEventListener('visibilitychange', onVisibilityChange)
    }

    // Iniciar polling
    poll()
  }

  const stop = () => {
    if (typeof document !== 'undefined')
      document.removeEventListener('visibilitychange', onVisibilityChange)

    if (!isRunning.value)
      return

    isRunning.value = false
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  const sync = (timestamp: string | null) => {
    if (!timestamp) {
      lastTimestamp.value = null
      return
    }

    const trimmed = timestamp.trim()
    lastTimestamp.value = trimmed.length ? trimmed : null
  }

  return {
    isRunning,
    lastTimestamp,
    start,
    stop,
    sync,
  }
}
