import api from './api'
import { useUIStore } from '@/stores/ui'
import type { Invoice, InvoiceListItem } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface InvoiceListFilters {
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

export const invoiceService = {
  listGlobal(params: InvoiceListFilters = {}): Promise<PaginatedResponse<InvoiceListItem>> {
    return api.get('/invoices', { params }).then(r => r.data)
  },

  get(customerId: number, opportunityId: number): Promise<Invoice> {
    return api.get(`/customers/${customerId}/opportunities/${opportunityId}/invoice`).then(r => r.data.data)
  },

  create(customerId: number, opportunityId: number): Promise<Invoice> {
    return api.post(`/customers/${customerId}/opportunities/${opportunityId}/invoice`).then(r => r.data.data)
  },

  pdfUrl(customerId: number, opportunityId: number, invoiceId: number): string {
    // Plain browser navigation carries no Accept-Language header, so the UI
    // locale travels as an explicit query param (see SetLocale middleware).
    const lang = useUIStore().locale
    return api.getUri({ url: `/customers/${customerId}/opportunities/${opportunityId}/invoice/${invoiceId}/pdf?lang=${lang}` })
  },

  previewUrl(customerId: number, opportunityId: number, invoiceId: number): string {
    const lang = useUIStore().locale
    return api.getUri({ url: `/customers/${customerId}/opportunities/${opportunityId}/invoice/${invoiceId}/preview?lang=${lang}` })
  },
}
