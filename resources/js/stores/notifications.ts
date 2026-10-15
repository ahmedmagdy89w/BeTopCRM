import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { notificationService } from '@/services/notificationService'
import type { AppNotification } from '@/types'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<AppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  let pollInterval: ReturnType<typeof setInterval> | null = null

  const hasUnread = computed(() => unreadCount.value > 0)

  async function fetchUnreadCount() {
    try {
      unreadCount.value = await notificationService.unreadCount()
    } catch {
      // silent — polling should not break UI
    }
  }

  async function fetchNotifications() {
    loading.value = true
    try {
      const res = await notificationService.list({ per_page: 20 })
      notifications.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function markRead(id: string) {
    await notificationService.markRead(id)
    const n = notifications.value.find(n => n.id === id)
    if (n) n.read_at = new Date().toISOString()
    if (unreadCount.value > 0) unreadCount.value--
  }

  async function markAllRead() {
    await notificationService.markAllRead()
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
  }

  async function remove(id: string) {
    await notificationService.delete(id)
    const idx = notifications.value.findIndex(n => n.id === id)
    if (idx !== -1) {
      const item = notifications.value[idx]
      if (item && !item.read_at && unreadCount.value > 0) unreadCount.value--
      notifications.value.splice(idx, 1)
    }
  }

  function startPolling(intervalMs = 60000) {
    stopPolling()
    fetchUnreadCount()
    pollInterval = setInterval(fetchUnreadCount, intervalMs)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  return {
    notifications,
    unreadCount,
    loading,
    hasUnread,
    fetchUnreadCount,
    fetchNotifications,
    markRead,
    markAllRead,
    remove,
    startPolling,
    stopPolling,
  }
})
