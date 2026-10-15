import api from './api'
import { useUIStore } from '@/stores/ui'
import type { Quotation, QuotationListItem } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface CreateQuotationPayload {
  valid_until?: string
  notes?: string
  terms?: string
}

export interface QuotationListFilters {
  page?: number
  per_page?: number
  search?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  status?: string
  customer_id?: number
  date_from?: string
  date_to?: string
}

export const quotationService = {
  listGlobal(params: QuotationListFilters = {}): Promise<PaginatedResponse<QuotationListItem>> {
    return api.get('/quotations', { params }).then(r => r.data)
  },

  list(customerId: number, opportunityId: number): Promise<Quotation[]> {
    return api.get(`/customers/${customerId}/opportunities/${opportunityId}/quotations`).then(r => r.data.data)
  },

  create(customerId: number, opportunityId: number, payload: CreateQuotationPayload = {}): Promise<Quotation> {
    return api.post(`/customers/${customerId}/opportunities/${opportunityId}/quotations`, payload).then(r => r.data.data)
  },

  send(customerId: number, opportunityId: number, quotationId: number): Promise<Quotation> {
    return api.post(`/customers/${customerId}/opportunities/${opportunityId}/quotations/${quotationId}/send`).then(r => r.data.data)
  },

  pdfUrl(customerId: number, opportunityId: number, quotationId: number): string {
    // Plain browser navigation carries no Accept-Language header, so the UI
    // locale travels as an explicit query param (see SetLocale middleware).
    const lang = useUIStore().locale
    return api.getUri({ url: `/customers/${customerId}/opportunities/${opportunityId}/quotations/${quotationId}/pdf?lang=${lang}` })
  },

  previewUrl(customerId: number, opportunityId: number, quotationId: number): string {
    const lang = useUIStore().locale
    return api.getUri({ url: `/customers/${customerId}/opportunities/${opportunityId}/quotations/${quotationId}/preview?lang=${lang}` })
  },
}
