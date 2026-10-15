import api from '@/services/api'
import type { OpportunityActivity, PaginatedResponse } from '@/types'
import type { ActivityChannel, ActivityOutcome } from '@/types/enums'
import type { OpportunityActivityType } from '@/types/models'

export interface OpportunityActivityPayload {
  type: OpportunityActivityType
  subject: string
  channel?: ActivityChannel
  description?: string
  due_date?: string
  reminder_at?: string
}

export const opportunityActivityService = {
  list(customerId: number, opportunityId: number, params: Record<string, unknown> = {}): Promise<PaginatedResponse<OpportunityActivity>> {
    return api.get(`/customers/${customerId}/opportunities/${opportunityId}/activities`, { params }).then(r => r.data)
  },

  create(customerId: number, opportunityId: number, data: OpportunityActivityPayload): Promise<OpportunityActivity> {
    return api.post(`/customers/${customerId}/opportunities/${opportunityId}/activities`, data).then(r => r.data.data)
  },

  update(customerId: number, opportunityId: number, activityId: number, data: Partial<OpportunityActivityPayload>): Promise<OpportunityActivity> {
    return api.put(`/customers/${customerId}/opportunities/${opportunityId}/activities/${activityId}`, data).then(r => r.data.data)
  },

  delete(customerId: number, opportunityId: number, activityId: number): Promise<void> {
    return api.delete(`/customers/${customerId}/opportunities/${opportunityId}/activities/${activityId}`)
  },

  complete(customerId: number, opportunityId: number, activityId: number, outcome?: ActivityOutcome): Promise<OpportunityActivity> {
    return api.patch(`/customers/${customerId}/opportunities/${opportunityId}/activities/${activityId}/complete`, { outcome }).then(r => r.data.data)
  },
}
