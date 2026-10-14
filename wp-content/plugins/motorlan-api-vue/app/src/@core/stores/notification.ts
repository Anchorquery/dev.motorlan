import { defineStore } from 'pinia'
import type { Notification } from '@layouts/types'
import { useApi } from '@/composables/useApi'

// La API añade created_at (ISO UTC) para formatear la fecha relativa en el idioma del usuario
export type ApiNotification = Notification & { created_at?: string | null }

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as ApiNotification[],
    unreadCount: 0,
  }),

  actions: {
    async fetchNotifications() {
      const { data, error } = await useApi('/wp-json/motorlan/v1/notifications').get().json<ApiNotification[]>()
      if (error.value) {
        console.error('Error fetching notifications:', error.value)
        return
      }
      if (Array.isArray(data.value)) {
        this.notifications = data.value
        // El contador real sale del endpoint (la lista solo trae las 20 últimas)
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
        this.unreadCount = Number(data.value.count) || 0
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

    // "Marcar como no leídas" (antes no hacía nada)
    async markNotificationsAsUnread(notificationIds: number[]) {
      if (!notificationIds.length)
        return

      const { error } = await useApi('/wp-json/motorlan/v1/notifications/unread').post({ notification_ids: notificationIds }).json()

      if (error.value) {
        console.error('Error marking notifications as unread:', error.value)
        return
      }

      this.notifications.forEach(notification => {
        if (notificationIds.includes(notification.id) && notification.isSeen) {
          notification.isSeen = false
          this.unreadCount++
        }
      })
    },

    // Eliminar también en el servidor (antes solo se quitaba en local y reaparecía al recargar)
    async removeNotification(notificationId: number) {
      const index = this.notifications.findIndex(item => item.id === notificationId)
      if (index === -1)
        return

      const { error } = await useApi(`/wp-json/motorlan/v1/notifications/${notificationId}`).delete().json()
      if (error.value) {
        console.error('Error deleting notification:', error.value)
        return
      }

      const current = this.notifications.findIndex(item => item.id === notificationId)
      if (current !== -1) {
        if (!this.notifications[current].isSeen)
          this.unreadCount = Math.max(0, this.unreadCount - 1)

        this.notifications.splice(current, 1)
      }
    },
  },
})
