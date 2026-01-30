import { defineStore } from 'pinia'
import type { Notification } from '@layouts/types'
import { useApi } from '@/composables/useApi'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as Notification[],
    unreadCount: 0,
  }),

  actions: {
    async fetchNotifications() {
      const { data, error } = await useApi('/wp-json/motorlan/v1/notifications').get().json<Notification[]>()
      if (error.value) {
        console.error('Error fetching notifications:', error.value)
        return
      }
      if (data.value) {
        this.notifications = data.value
        // Update unread count based on fetched notifications if needed, 
        // but better to use the specific endpoint for accuracy
        this.fetchUnreadCount()
      }
    },

    async fetchUnreadCount() {
      const { data, error } = await useApi('/wp-json/motorlan/v1/notifications/unread-count').get().json<{ count: number }>()
      if (error.value) {
        console.error('Error fetching unread count:', error.value)
        return
      }
      if (data.value)
        this.unreadCount = data.value.count
    },

    async markNotificationsAsRead(notificationIds: number[]) {
      const { error } = await useApi('/wp-json/motorlan/v1/notifications/read').post({ notification_ids: notificationIds }).json()

      if (error.value) {
        console.error('Error marking notifications as read:', error.value)
        return
      }

      // Update the state locally
      this.notifications.forEach(notification => {
        if (notificationIds.includes(notification.id)) {
          if (!notification.isSeen) {
            notification.isSeen = true
            this.unreadCount = Math.max(0, this.unreadCount - 1)
          }
        }
      })
    },

    removeNotification(notificationId: number) {
      const index = this.notifications.findIndex(item => item.id === notificationId)
      if (index !== -1) {
        const notification = this.notifications[index]
        if (!notification.isSeen)
          this.unreadCount = Math.max(0, this.unreadCount - 1)

        this.notifications.splice(index, 1)
      }
    },
  },
})
