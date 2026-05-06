import { type ComputedRef, type Ref, isRef, ref, unref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

export interface PollingOptions {
  minInterval?: number
  maxInterval?: number
  backoffMultiplier?: number
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
  const options = typeof intervalMsOrOptions === 'number'
    ? { minInterval: intervalMsOrOptions, maxInterval: intervalMsOrOptions, backoffMultiplier: 1 }
    : { minInterval: 3000, maxInterval: 15000, backoffMultiplier: 1.5, ...intervalMsOrOptions }

  const isRunning = ref(false)
  const lastTimestamp = ref<string | null>(null)
  const currentInterval = ref(options.minInterval)
  const consecutiveEmptyPolls = ref(0)
  let timer: any | null = null

  const fetchUpdates = async () => {
    try {
      let url = unref(urlBase)
      if (lastTimestamp.value) {
        const params = new URLSearchParams({ since_timestamp: lastTimestamp.value })
        url += (url.includes('?') ? '&' : '?') + params.toString()
      }

      const { data, error, statusCode } = await useApi<any>(createUrl(url)).get().json()

      // Manejar rate limiting (429)
      if (statusCode.value === 429) {
        // Si nos limitan, esperar 60 segundos o el maxInterval
        const retryAfter = 60000
        if (timer) clearTimeout(timer)
        timer = setTimeout(poll, retryAfter)
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

  const poll = () => {
    if (!isRunning.value) return

    // Ejecutar fetch
    void fetchUpdates().finally(() => {
      // Programar siguiente ejecución solo después de terminar la actual
      // esto previene solapamiento de peticiones lentas
      if (isRunning.value) {
        timer = setTimeout(poll, currentInterval.value)
      }
    })
  }

  const start = () => {
    if (isRunning.value)
      return

    isRunning.value = true
    currentInterval.value = options.minInterval ?? 3000
    consecutiveEmptyPolls.value = 0

    // Iniciar polling
    poll()
  }

  const stop = () => {
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
