import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

/**
 * Composable genérico para generar un polling a una REST API.
 * 
 * @param urlBase - URL base del endpoint (sin parámetros)
 * @param intervalMs - Intervalo en milisegundos (por defecto 3000)
 * @param onData - Callback que se ejecuta cuando hay nuevos datos
 */
export function usePolling(urlBase: string, onData: (data: any) => void, intervalMs = 3000) {
  const isRunning = ref(false)
  const lastTimestamp = ref<string | null>(null)
  let timer: number | null = null

  const fetchUpdates = async () => {
    try {
      let url = urlBase
      if (lastTimestamp.value) {
        const params = new URLSearchParams({ since_timestamp: lastTimestamp.value })
        url += `?${params.toString()}`
      }

      const { data, error } = await useApi<any>(createUrl(url)).get().json()

      if (!error.value && data.value?.data?.length) {
        onData(data.value.data)
        const serverTs = data.value?.meta?.server_timestamp
        if (serverTs)
          lastTimestamp.value = serverTs
      }
    } catch (err) {
      console.error('Polling error:', err)
    }
  }

  const start = () => {
    if (isRunning.value) return
    isRunning.value = true
    fetchUpdates()
    timer = window.setInterval(fetchUpdates, intervalMs)
  }

  const stop = () => {
    isRunning.value = false
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  onMounted(start)
  onBeforeUnmount(stop)

  return {
    isRunning,
    start,
    stop,
  }
}