/**
 * ARCHIVO OBSOLETO: usePusherChannel.ts
 *
 * Este archivo fue reemplazado por el sistema de polling basado en REST (usePolling.ts).
 * Ya no utiliza Pusher ni pusher-js. Se mantiene solo para evitar errores de imports antiguos.
 */

import { ref } from 'vue'

export const usePusherChannel = () => {
  const isConnected = ref(false)
  const error = ref<string | null>(null)

  console.warn('[Motorlan] usePusherChannel.ts está obsoleto. Usa usePolling.ts en su lugar.')

  return {
    isConnected,
    error,
    connect: () => false,
    disconnect: () => {},
    bind: () => () => {},
    unbind: () => {},
    client: null,
    channel: null,
    isConfigured: false,
  }
}

export type UsePusherChannelReturn = ReturnType<typeof usePusherChannel>
