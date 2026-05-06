import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

/**
 * Composable generico para realizar polling contra un endpoint REST.
 *
 * @param urlBase - URL base del endpoint (sin parametros).
 * @param onData - Callback que recibe los datos nuevos y la metadata cruda.
 * @param intervalMs - Intervalo en milisegundos (por defecto 3000).
 */
export function usePolling(urlBase: string, onData: (data: any[], meta: any | null) => void, intervalMs = 3000) {
  const isRunning = ref(false)
  const lastTimestamp = ref<string | null>(null)
  let timer: number | null = null

  const fetchUpdates = async () => {
    try {
      let url = urlBase
      if (lastTimestamp.value) {
        const params = new URLSearchParams({ since_timestamp: lastTimestamp.value })
        url += (url.includes('?') ? '&' : '?') + params.toString()
      }

      const { data, error } = await useApi<any>(createUrl(url)).get().json()

      if (!error.value && data.value) {
        const payload = Array.isArray(data.value?.data) ? data.value.data : []
        const meta = data.value?.meta ?? null

        if (payload.length)
          onData(payload, meta)
        else if (meta)
          onData([], meta)

        const serverTs = meta?.server_timestamp ?? data.value?.meta?.server_timestamp
        if (serverTs)
          lastTimestamp.value = serverTs
      }
    }
    catch (err) {
      console.error('Polling error:', err)
    }
  }

  const start = () => {
    if (isRunning.value)
      return

    isRunning.value = true
    void fetchUpdates()
    timer = window.setInterval(fetchUpdates, intervalMs)
  }

  const stop = () => {
    if (!isRunning.value)
      return

    isRunning.value = false
    if (timer !== null) {
      clearInterval(timer)
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
