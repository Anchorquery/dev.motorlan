<script lang="ts" setup>
import { useNotificationStore } from '@/@core/stores/notification'
import type { Notification } from '@layouts/types'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const store = useNotificationStore()

const notifications = computed(() => store.notifications)

onMounted(() => {
  store.fetchNotifications()
})

const removeNotification = (notificationId: number) => {
  store.removeNotification(notificationId)
}

const markRead = (notificationId: number[]) => {
  store.markNotificationsAsRead(notificationId)
}

// The backend does not currently support marking as unread,
// but we can leave the function here for future implementation.
const markUnRead = (notificationId: number[]) => {
  // console.log('Mark as unread:', notificationId)
}

const router = useRouter()

const getVueBase = (): string => {
  return (window as unknown as { wpData?: { vue_base?: string } }).wpData?.vue_base || '/'
}

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    markRead([notification.id])

  // vue_path = path relative to router base (preferred, no base prefix)
  // url = full path including base prefix (fallback with strip)
  const vuePath = notification.data?.vue_path
  const targetUrl = notification.data?.url

  if (!vuePath && !targetUrl)
    return

  const vueBase = getVueBase().replace(/\/$/, '')

  // Decide nav SPA vs full page load:
  // - vue_path siempre SPA (relativo router).
  // - url: si arranca con vueBase → strip y SPA. Si no → cross-base, window.location.
  if (vuePath) {
    const routePayload: { path: string; query?: Record<string, string> } = { path: vuePath }
    if (notification.data?.offer_id)
      routePayload.query = { offer_id: String(notification.data.offer_id) }
    router.push(routePayload)

    return
  }

  if (targetUrl!.startsWith(vueBase)) {
    const stripped = targetUrl!.slice(vueBase.length) || '/'
    const routePayload: { path: string; query?: Record<string, string> } = { path: stripped }
    if (notification.data?.offer_id)
      routePayload.query = { offer_id: String(notification.data.offer_id) }
    router.push(routePayload)

    return
  }

  // Cross-base URL (ej notif lleva a marketplace desde mi-cuenta): full nav
  const finalUrl = notification.data?.offer_id
    ? `${targetUrl}${targetUrl!.includes('?') ? '&' : '?'}offer_id=${notification.data.offer_id}`
    : targetUrl!

  window.location.href = finalUrl
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
