import { defineStore } from 'pinia'
import type { Notification } from '@layouts/types'
import { useApi } from '@/composables/useApi'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as Notification[],
  }),

  actions: {
    async fetchNotifications() {
      const { data, error } = await useApi('/notifications').get().json<Notification[]>()
      if (error.value) {
        console.error('Error fetching notifications:', error.value)
        return
      }
      if (data.value)
        this.notifications = data.value
    },

    async markNotificationsAsRead(notificationIds: number[]) {
      const { error } = await useApi('/notifications/read').post({ notification_ids: notificationIds }).json()

      if (error.value) {
        console.error('Error marking notifications as read:', error.value)
        return
      }
      
      // Update the state locally
      this.notifications.forEach(notification => {
        if (notificationIds.includes(notification.id))
          notification.isSeen = true
      })
    },

    removeNotification(notificationId: number) {
      this.notifications.forEach((item, index) => {
        if (notificationId === item.id)
          this.notifications.splice(index, 1)
      })
    },
  },
})