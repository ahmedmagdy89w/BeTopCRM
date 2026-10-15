import api from '@/services/api'
import type { OpportunityProduct } from '@/types'

export const opportunityProductService = {
  list(customerId: number, opportunityId: number): Promise<OpportunityProduct[]> {
    return api.get(`/customers/${customerId}/opportunities/${opportunityId}/products`).then(r => r.data.data)
  },

  attach(customerId: number, opportunityId: number, data: { product_id: number; quantity?: number; unit_price?: string | number; discount_percent?: string | number | null; discount_amount?: string | number; notes?: string }): Promise<void> {
    return api.post(`/customers/${customerId}/opportunities/${opportunityId}/products`, data)
  },

  update(customerId: number, opportunityId: number, productId: number, data: { quantity?: number; unit_price?: string | number; discount_percent?: string | number | null; discount_amount?: string | number; notes?: string }): Promise<void> {
    return api.put(`/customers/${customerId}/opportunities/${opportunityId}/products/${productId}`, data)
  },

  detach(customerId: number, opportunityId: number, productId: number): Promise<void> {
    return api.delete(`/customers/${customerId}/opportunities/${opportunityId}/products/${productId}`)
  },
}
