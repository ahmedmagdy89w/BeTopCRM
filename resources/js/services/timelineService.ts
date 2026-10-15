import api from '@/services/api'
import type { TimelineEvent, PaginatedResponse } from '@/types'

export interface TimelineFilters {
  type?: string
  from?: string
  to?: string
  user_id?: number
  page?: number
  per_page?: number
}

export const timelineService = {
  forEntity(entityType: string, entityId: number, params: TimelineFilters = {}): Promise<PaginatedResponse<TimelineEvent>> {
    return api.get(`/${entityType}/${entityId}/timeline`, { params }).then(r => r.data)
  },
}
