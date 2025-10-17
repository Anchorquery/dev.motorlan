import { ref, computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { usePolling } from '@/composables/usePolling'

// Interfaces
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

export function usePurchaseChat(uuid: string) {
  const purchaseData = ref<any>(null)
  const purchaseError = ref<any>(null)
  const messages = ref<PurchaseMessage[]>([])
  const isLoading = ref(false)
  const isPollingActive = ref(false)
  const polling = ref<any>(null)

  const purchase = computed(() => purchaseData.value?.data ?? null)

  // Fetch initial data
  const fetchPurchaseDetails = async () => {
    isLoading.value = true
    const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}`)).get().json()
    purchaseData.value = data.value
    purchaseError.value = error.value
    isLoading.value = false
  }

  const fetchMessages = async () => {
    const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}/messages`)).get().json()
    if (data.value?.data) {
      messages.value = data.value.data
    }
  }

  // Send a new message
  const sendMessage = async (messageText: string) => {
    if (!messageText.trim()) return

    const { data, error } = await useApi<any>(createUrl(`/wp-json/motorlan/v1/purchases/${uuid}/messages`))
      .post({ message: messageText.trim() })
      .json()

    if (data.value?.data) {
      messages.value.push(data.value.data)
    }
    // Handle error
  }

  // Setup polling to replace realtime updates
  const setupPolling = () => {
    if (polling.value) return

    const endpointUrl = `/wp-json/motorlan/v1/purchases/${uuid}/messages`

    polling.value = usePolling(endpointUrl, (newMessages: PurchaseMessage[]) => {
      if (newMessages.length) {
        console.info('Mensajes nuevos detectados mediante polling:', newMessages.length)
        messages.value = [...messages.value, ...newMessages]
      }
    }, 3000)

    isPollingActive.value = true
  }

  return {
    purchase,
    purchaseError,
    messages,
    isLoading,
    isPollingActive,
    fetchPurchaseDetails,
    fetchMessages,
    sendMessage,
    setupPolling,
  }
}
