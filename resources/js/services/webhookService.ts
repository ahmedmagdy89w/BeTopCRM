import api from './api'
import type { WebhookEndpoint } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface WebhookDelivery {
  id: number
  event: string
  response_status: number | null
  status: 'pending' | 'success' | 'failed'
  attempts: number
  delivered_at: string | null
  created_at: string
}

export const webhookService = {
  list(params?: Record<string, unknown>): Promise<PaginatedResponse<WebhookEndpoint>> {
    return api.get('/webhooks', { params }).then(r => r.data)
  },
  get(id: number): Promise<WebhookEndpoint> {
    return api.get(`/webhooks/${id}`).then(r => r.data.data)
  },
  create(data: Partial<WebhookEndpoint>): Promise<WebhookEndpoint> {
    return api.post('/webhooks', data).then(r => r.data.data)
  },
  update(id: number, data: Partial<WebhookEndpoint>): Promise<WebhookEndpoint> {
    return api.put(`/webhooks/${id}`, data).then(r => r.data.data)
  },
  delete(id: number): Promise<void> {
    return api.delete(`/webhooks/${id}`)
  },
  rotateSecret(id: number): Promise<{ secret: string }> {
    return api.post(`/webhooks/${id}/rotate-secret`).then(r => r.data.data)
  },
  deliveries(id: number, params?: Record<string, unknown>): Promise<PaginatedResponse<WebhookDelivery>> {
    return api.get(`/webhooks/${id}/deliveries`, { params }).then(r => r.data)
  },
  redeliver(deliveryId: number): Promise<void> {
    return api.post(`/webhook-deliveries/${deliveryId}/redeliver`)
  },
}
