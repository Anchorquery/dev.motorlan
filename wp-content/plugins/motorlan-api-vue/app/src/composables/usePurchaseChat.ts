import { ref, computed } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import Pusher from 'pusher-js'

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
  const pusherClient = ref<Pusher | null>(null)
  const isRealtimeConnected = ref(false)

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

  // Setup Pusher for real-time messages
  const setupRealtime = () => {
    if (pusherClient.value) return

    const PUSHER_APP_KEY = import.meta.env.VITE_PUSHER_APP_KEY?.toString()
    const PUSHER_APP_CLUSTER = import.meta.env.VITE_PUSHER_APP_CLUSTER?.toString()
    const PUSHER_AUTH_ENDPOINT = `${import.meta.env.VITE_API_BASE_URL}/wp-json/motorlan/v1/purchases/pusher/auth`
    const channelName = `private-purchase-${uuid}`

    if (!PUSHER_APP_KEY) {
      console.error('Pusher key not found.')
      return
    }

    pusherClient.value = new Pusher(PUSHER_APP_KEY, {
      cluster: PUSHER_APP_CLUSTER,
      forceTLS: true,
      authEndpoint: PUSHER_AUTH_ENDPOINT,
    })

    const channel = pusherClient.value.subscribe(channelName)

    channel.bind('pusher:subscription_succeeded', () => {
      console.info('Connected to Pusher channel:', channelName)
      isRealtimeConnected.value = true
    })

    channel.bind('purchase.message', async () => {
      console.info('New message detected via Pusher')
      await fetchMessages()
    })
  }

  return {
    purchase,
    purchaseError,
    messages,
    isLoading,
    isRealtimeConnected,
    fetchPurchaseDetails,
    fetchMessages,
    sendMessage,
    setupRealtime,
  }
}
