import api from './api'
import type { DashboardData, ActivityFeedItem } from '@/types'
import type { PaginatedResponse } from '@/types'

export interface DashboardFilters {
  date_from?: string
  date_to?: string
}

export const dashboardService = {
  index(params?: DashboardFilters): Promise<DashboardData> {
    return api.get('/dashboard', { params }).then(r => r.data.data)
  },

  activityFeed(params?: { per_page?: number; page?: number }): Promise<PaginatedResponse<ActivityFeedItem>> {
    return api.get('/dashboard/activity-feed', { params }).then(r => r.data)
  },
}
