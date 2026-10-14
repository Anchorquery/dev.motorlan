<script lang="ts" setup>
import { useNotificationStore } from '@/@core/stores/notification'
import type { Notification } from '@layouts/types'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const store = useNotificationStore()
const { locale } = useI18n()

// Reloj para recalcular "hace X" sin pedir nada al servidor
const now = ref(Date.now())

// Fecha relativa en el idioma del usuario a partir de created_at (UTC). El texto "time"
// del servidor queda como respaldo (antes salía "5 minutos ago").
const formatRelative = (iso: string | null | undefined, fallback: string): string => {
  if (!iso)
    return fallback
  const ts = Date.parse(iso)
  if (Number.isNaN(ts))
    return fallback
  try {
    const rtf = new Intl.RelativeTimeFormat(String(locale.value || 'es'), { numeric: 'auto' })
    const diffSec = Math.round((ts - now.value) / 1000)
    const abs = Math.abs(diffSec)
    if (abs < 60)
      return rtf.format(diffSec, 'second')
    if (abs < 3600)
      return rtf.format(Math.round(diffSec / 60), 'minute')
    if (abs < 86400)
      return rtf.format(Math.round(diffSec / 3600), 'hour')
    if (abs < 86400 * 30)
      return rtf.format(Math.round(diffSec / 86400), 'day')

    return new Intl.DateTimeFormat(String(locale.value || 'es'), { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(ts))
  }
  catch {
    return fallback
  }
}

const notifications = computed<Notification[]>(() => store.notifications.map(n => ({
  ...n,
  time: formatRelative(n.created_at, n.time),
}) as Notification))

// Refresco cada 60 s con la pestaña visible y al volver a ella: antes la campana solo se
// cargaba al montar y un mensaje nuevo no aparecía hasta recargar la página
const REFRESH_MS = 60000
let refreshTimer: ReturnType<typeof setInterval> | null = null
let lastFetch = 0

const refresh = () => {
  now.value = Date.now()
  if (document.visibilityState !== 'visible')
    return
  lastFetch = Date.now()
  void store.fetchNotifications()
}

const onVisibilityChange = () => {
  // Al volver a la pestaña, refrescar si hace más de 30 s de la última consulta
  if (document.visibilityState === 'visible' && Date.now() - lastFetch > 30000)
    refresh()
}

onMounted(() => {
  refresh()
  refreshTimer = setInterval(refresh, REFRESH_MS)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(() => {
  if (refreshTimer)
    clearInterval(refreshTimer)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})

const removeNotification = (notificationId: number) => {
  void store.removeNotification(notificationId)
}

const markRead = (notificationId: number[]) => {
  store.markNotificationsAsRead(notificationId)
}

// Ahora sí existe en el backend (POST /notifications/unread)
const markUnRead = (notificationId: number[]) => {
  void store.markNotificationsAsUnread(notificationId)
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
  // vue-router descarta "?room_key=..." si va dentro de `path`: separar ruta y query
  const toRoutePayload = (rawPath: string) => {
    const parsed = new URL(rawPath, 'http://local')
    const query: Record<string, string> = Object.fromEntries(parsed.searchParams.entries())
    if (notification.data?.offer_id)
      query.offer_id = String(notification.data.offer_id)

    return { path: parsed.pathname, query }
  }

  if (vuePath) {
    router.push(toRoutePayload(vuePath))

    return
  }

  if (targetUrl!.startsWith(vueBase)) {
    const stripped = targetUrl!.slice(vueBase.length) || '/'
    const routePayload = toRoutePayload(stripped)
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
    :unread-count="store.unreadCount"
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
