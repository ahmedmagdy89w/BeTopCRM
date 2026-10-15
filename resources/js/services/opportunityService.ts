import api from '@/services/api'
import type { Opportunity, PaginatedResponse } from '@/types'

export interface OpportunityFilters {
  page?: number
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  search?: string
  stage?: string
  status?: 'open' | 'closed'
  assigned_to?: number
  amount_min?: number
  amount_max?: number
  date_from?: string
  date_to?: string
}

export const opportunityService = {
  // Global board/table
  listGlobal(params: OpportunityFilters = {}): Promise<PaginatedResponse<Opportunity>> {
    return api.get('/opportunities', { params }).then(r => r.data)
  },

  list(customerId: number, params: OpportunityFilters = {}): Promise<PaginatedResponse<Opportunity>> {
    return api.get(`/customers/${customerId}/opportunities`, { params }).then(r => r.data)
  },

  get(customerId: number, id: number): Promise<Opportunity> {
    return api.get(`/customers/${customerId}/opportunities/${id}`).then(r => r.data.data)
  },

  create(customerId: number, data: Partial<Opportunity>): Promise<Opportunity> {
    return api.post(`/customers/${customerId}/opportunities`, data).then(r => r.data.data)
  },

  update(customerId: number, id: number, data: Partial<Opportunity>): Promise<Opportunity> {
    return api.put(`/customers/${customerId}/opportunities/${id}`, data).then(r => r.data.data)
  },

  delete(customerId: number, id: number): Promise<void> {
    return api.delete(`/customers/${customerId}/opportunities/${id}`)
  },

  updateStage(customerId: number, id: number, stage: string): Promise<Opportunity> {
    return api.patch(`/customers/${customerId}/opportunities/${id}/stage`, { stage }).then(r => r.data.data)
  },
}
