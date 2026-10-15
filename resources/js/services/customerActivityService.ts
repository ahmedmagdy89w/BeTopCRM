import api from '@/services/api'
import type { CustomerActivity, PaginatedResponse } from '@/types'
import type { ActivityChannel, CustomerActivityType, ActivityOutcome } from '@/types/enums'

export interface CustomerActivityPayload {
  type: CustomerActivityType
  channel?: ActivityChannel
  subject: string
  description?: string
  due_date?: string
  reminder_at?: string
}

export const customerActivityService = {
  list(customerId: number, params: Record<string, unknown> = {}): Promise<PaginatedResponse<CustomerActivity>> {
    return api.get(`/customers/${customerId}/activities`, { params }).then(r => r.data)
  },

  listGlobal(params: Record<string, unknown> = {}): Promise<PaginatedResponse<CustomerActivity>> {
    return api.get('/activities', { params }).then(r => r.data)
  },

  create(customerId: number, data: CustomerActivityPayload): Promise<CustomerActivity> {
    return api.post(`/customers/${customerId}/activities`, data).then(r => r.data.data)
  },

  update(customerId: number, activityId: number, data: Partial<CustomerActivityPayload>): Promise<CustomerActivity> {
    return api.put(`/customers/${customerId}/activities/${activityId}`, data).then(r => r.data.data)
  },

  delete(customerId: number, activityId: number): Promise<void> {
    return api.delete(`/customers/${customerId}/activities/${activityId}`)
  },

  complete(customerId: number, activityId: number, outcome?: ActivityOutcome): Promise<CustomerActivity> {
    return api.patch(`/customers/${customerId}/activities/${activityId}/complete`, { outcome }).then(r => r.data.data)
  },
}
