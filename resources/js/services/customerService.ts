import api from '@/services/api'
import type { Customer, PaginatedResponse } from '@/types'

export interface CustomerFilters {
  page?: number
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  search?: string
  type?: 'individual' | 'company'
  is_lead?: boolean
  lead_status?: string
  status?: string
  source?: string
  assigned_to?: number
  account_manager_id?: number
  health_below?: number
  follow_up_due?: boolean
  contact?: string
  city?: string
  address?: string
  score_min?: number
  score_max?: number
  tag?: string | number
}

export const customerService = {
  list(params: CustomerFilters = {}): Promise<PaginatedResponse<Customer>> {
    return api.get('/customers', { params }).then(r => r.data)
  },

  get(id: number): Promise<Customer> {
    return api.get(`/customers/${id}`).then(r => r.data.data)
  },

  create(data: Partial<Customer>): Promise<Customer> {
    return api.post('/customers', data).then(r => r.data.data)
  },

  update(id: number, data: Partial<Customer>): Promise<Customer> {
    return api.put(`/customers/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/customers/${id}`)
  },

  updateLeadStatus(id: number, lead_status: string): Promise<Customer> {
    return api.patch(`/customers/${id}/lead-status`, { lead_status }).then(r => r.data.data)
  },

  assign(id: number, userId: number): Promise<Customer> {
    return api.patch(`/customers/${id}/assign`, { assigned_to: userId }).then(r => r.data.data)
  },

  convert(id: number): Promise<Customer> {
    return api.post(`/customers/${id}/convert`).then(r => r.data.data)
  },

  score(id: number): Promise<{ score: number | null; breakdown: Record<string, number>; scored_at: string | null }> {
    return api.get(`/customers/${id}/score`).then(r => r.data.data)
  },

  recalculateScore(id: number): Promise<{ score: number; breakdown: Record<string, number>; scored_at: string }> {
    return api.post(`/customers/${id}/score/recalculate`).then(r => r.data.data)
  },

  syncTags(id: number, tagIds: number[]): Promise<void> {
    return api.post(`/customers/${id}/tags`, { tag_ids: tagIds })
  },
}
