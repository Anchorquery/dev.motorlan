import { defineStore } from 'pinia'
import type { Notification } from '@layouts/types'
import api from '@/services/api'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as Notification[],
  }),

  actions: {
    async fetchNotifications() {
      try {
        const { data } = await api.get<Notification[]>('/notifications')
        this.notifications = data
      }
      catch (error) {
        console.error('Error fetching notifications:', error)
      }
    },

    async markNotificationsAsRead(notificationIds: number[]) {
      try {
        await api.post('/notifications/read', { notification_ids: notificationIds })
        
        // Update the state locally
        this.notifications.forEach(notification => {
          if (notificationIds.includes(notification.id))
            notification.isSeen = true
        })
      }
      catch (error) {
        console.error('Error marking notifications as read:', error)
      }
    },

    removeNotification(notificationId: number) {
      this.notifications.forEach((item, index) => {
        if (notificationId === item.id)
          this.notifications.splice(index, 1)
      })
    },
  },
})