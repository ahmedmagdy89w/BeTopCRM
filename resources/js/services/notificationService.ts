import api from './api'
import type { AppNotification } from '@/types'
import type { PaginatedResponse } from '@/types'

export interface NotificationFilters {
  unread_only?: boolean
  entity_type?: string
  per_page?: number
  page?: number
}

export const notificationService = {
  list(params?: NotificationFilters): Promise<PaginatedResponse<AppNotification>> {
    return api.get('/notifications', { params }).then(r => r.data)
  },

  unreadCount(): Promise<number> {
    return api.get('/notifications/unread-count').then(r => r.data.data.count)
  },

  markRead(id: string): Promise<void> {
    return api.patch(`/notifications/${id}/read`)
  },

  markAllRead(): Promise<void> {
    return api.patch('/notifications/read-all')
  },

  delete(id: string): Promise<void> {
    return api.delete(`/notifications/${id}`)
  },
}
