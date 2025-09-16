<script lang="ts" setup>
import { useNotificationStore } from '@/@core/stores/notification'
import type { Notification } from '@layouts/types'
import { onMounted } from 'vue'

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

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    markRead([notification.id])
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
