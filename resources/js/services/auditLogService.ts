import api from './api'
import type { AuditLog } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface AuditLogFilters {
  page?: number
  per_page?: number
  user_id?: number
  entity_type?: string
  action?: string
  date_from?: string
  date_to?: string
}

export const auditLogService = {
  list(params?: AuditLogFilters): Promise<PaginatedResponse<AuditLog>> {
    return api.get('/audit-logs', { params }).then(r => r.data)
  },
  forEntity(type: string, id: number): Promise<PaginatedResponse<AuditLog>> {
    return api.get(`/audit-logs/entity/${type}/${id}`).then(r => r.data)
  },
}
