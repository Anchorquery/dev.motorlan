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

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    markRead([notification.id])

  const targetUrl = notification.data?.url
  if (!targetUrl)
    return

  const routePayload: { path: string; query?: Record<string, string> } = {
    path: targetUrl,
  }

  if (notification.data?.offer_id) {
    routePayload.query = {
      ...routePayload.query,
      offer_id: String(notification.data.offer_id),
    }
  }

  router.push(routePayload)
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
