import api from './api'
import type { Campaign } from '@/types'
import type { PaginatedResponse } from '@/types'

export interface CampaignFilters {
  page?: number
  per_page?: number
  status?: string
  search?: string
}

export interface ScheduleRequest {
  scheduled_at: string
}

export interface CampaignRecipient {
  id: number
  email: string
  status: 'pending' | 'sent' | 'failed'
  sent_at?: string | null
  failed_reason?: string | null
  customer?: { id: number; name: string | null }
}

export const campaignService = {
  list(params?: CampaignFilters): Promise<PaginatedResponse<Campaign>> {
    return api.get('/campaigns', { params }).then(r => r.data)
  },

  get(id: number): Promise<Campaign> {
    return api.get(`/campaigns/${id}`).then(r => r.data.data)
  },

  create(data: Record<string, unknown>): Promise<Campaign> {
    return api.post('/campaigns', data).then(r => r.data.data)
  },

  update(id: number, data: Record<string, unknown>): Promise<Campaign> {
    return api.put(`/campaigns/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/campaigns/${id}`)
  },

  send(id: number): Promise<Campaign> {
    return api.post(`/campaigns/${id}/send`).then(r => r.data.data)
  },

  schedule(id: number, data: ScheduleRequest): Promise<Campaign> {
    return api.post(`/campaigns/${id}/schedule`, data).then(r => r.data.data)
  },

  cancel(id: number): Promise<Campaign> {
    return api.post(`/campaigns/${id}/cancel`).then(r => r.data.data)
  },

  recipients(id: number, params?: { page?: number; per_page?: number }): Promise<PaginatedResponse<CampaignRecipient>> {
    return api.get(`/campaigns/${id}/recipients`, { params }).then(r => r.data)
  },
}
