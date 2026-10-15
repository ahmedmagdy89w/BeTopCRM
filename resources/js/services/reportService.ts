import api from './api'
import type {
  UserPerformanceRow,
  UserPerformanceSummary,
  UserProductPerformanceRow,
  UserProductPerformanceSummary,
  SalesReportRow,
  SalesReportSummary,
  CompanyReportRow,
  CompanyReportSummary,
  CustomerReportRow,
  CustomerReportSummary,
  LeadPipelineReport,
  ProductPerformanceReport,
  AsyncJob,
} from '@/types'

export interface ReportDateFilters {
  date_from?: string
  date_to?: string
}

export interface ReportPaging {
  page?: number
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
}

export interface ReportPageMeta {
  current_page: number
  per_page: number
  total: number
}

export interface UserPerformanceFilters extends ReportDateFilters {
  user_id?: number
}

export interface CompanyReportFilters extends ReportDateFilters, ReportPaging {
  organization_id?: number
  industry?: string
}

export interface CustomerReportFilters extends ReportDateFilters, ReportPaging {
  customer_id?: number
  organization_id?: number
  account_manager_id?: number
  status?: string
  type?: string
  health_below?: number
}

export interface LeadPipelineFilters extends ReportDateFilters {
  assigned_to?: number
  customer_id?: number
  status?: string
  source?: string
  min_score?: number
}

export interface ProductPerformanceFilters extends ReportDateFilters {
  category_id?: number
}

export interface UserProductPerformanceFilters extends ReportDateFilters, ReportPaging {
  user_id?: number
  product_id?: number
}

export interface SalesReportFilters extends ReportDateFilters {
  user_id?: number
  group_by?: 'month' | 'year'
}

export type ExportFormat = 'xlsx' | 'pdf'

export interface ReportResponse<T, S> {
  data: T[]
  summary: S
}

export interface PagedReportResponse<T, S> extends ReportResponse<T, S> {
  meta?: ReportPageMeta
}

async function downloadBlob(path: string, params: Record<string, unknown>, format: ExportFormat): Promise<void> {
  const response = await api.get(path, {
    params: { ...params, format },
    responseType: 'blob',
  })
  const disposition: string = response.headers['content-disposition'] ?? ''
  const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
  const filename = match?.[1]?.replace(/['"]/g, '') ?? `report.${format}`
  const url = URL.createObjectURL(response.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

export const reportService = {
  // --- Synchronous report fetching ---
  userPerformance(params?: UserPerformanceFilters): Promise<ReportResponse<UserPerformanceRow, UserPerformanceSummary>> {
    return api.get('/reports/user-performance', { params }).then(r => r.data)
  },

  company(params?: CompanyReportFilters): Promise<PagedReportResponse<CompanyReportRow, CompanyReportSummary>> {
    return api.get('/reports/company', { params }).then(r => r.data)
  },

  customer(params?: CustomerReportFilters): Promise<PagedReportResponse<CustomerReportRow, CustomerReportSummary>> {
    return api.get('/reports/customer', { params }).then(r => r.data)
  },

  leadPipeline(params?: LeadPipelineFilters): Promise<LeadPipelineReport> {
    return api.get('/reports/lead-pipeline', { params }).then(r => r.data)
  },

  exportLeadPipeline(params?: LeadPipelineFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/lead-pipeline/export', params as Record<string, unknown> ?? {}, format)
  },

  productPerformance(params?: ProductPerformanceFilters): Promise<ProductPerformanceReport> {
    return api.get('/reports/product-performance', { params }).then(r => r.data)
  },

  exportProductPerformance(params?: ProductPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/product-performance/export', params as Record<string, unknown> ?? {}, format)
  },

  userProductPerformance(params?: UserProductPerformanceFilters): Promise<PagedReportResponse<UserProductPerformanceRow, UserProductPerformanceSummary>> {
    return api.get('/reports/user-product-performance', { params }).then(r => r.data)
  },

  exportUserProductPerformance(params?: UserProductPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/user-product-performance/export', params as Record<string, unknown> ?? {}, format)
  },

  sales(params?: SalesReportFilters): Promise<ReportResponse<SalesReportRow, SalesReportSummary>> {
    return api.get('/reports/sales', { params }).then(r => r.data)
  },

  exportSales(params?: SalesReportFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/sales/export', params as Record<string, unknown> ?? {}, format)
  },

  // --- Authenticated blob exports ---
  exportUserPerformance(params?: UserPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/user-performance/export', params as Record<string, unknown> ?? {}, format)
  },

  exportCompany(params?: CompanyReportFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/company/export', params as Record<string, unknown> ?? {}, format)
  },

  exportCustomer(params?: CustomerReportFilters, format: ExportFormat = 'xlsx'): Promise<void> {
    return downloadBlob('/reports/customer/export', params as Record<string, unknown> ?? {}, format)
  },

  // --- Async report generation (queued) ---
  userPerformanceAsync(params?: UserPerformanceFilters): Promise<AsyncJob> {
    return api.post('/reports/user-performance/async', params).then(r => r.data.data)
  },

  companyAsync(params?: CompanyReportFilters): Promise<AsyncJob> {
    return api.post('/reports/company/async', params).then(r => r.data.data)
  },

  customerAsync(params?: CustomerReportFilters): Promise<AsyncJob> {
    return api.post('/reports/customer/async', params).then(r => r.data.data)
  },

  leadPipelineAsync(params?: LeadPipelineFilters): Promise<AsyncJob> {
    return api.post('/reports/lead-pipeline/async', params).then(r => r.data.data)
  },

  exportLeadPipelineAsync(params?: LeadPipelineFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/lead-pipeline/export/async', { ...params, format }).then(r => r.data.data)
  },

  productPerformanceAsync(params?: ProductPerformanceFilters): Promise<AsyncJob> {
    return api.post('/reports/product-performance/async', params).then(r => r.data.data)
  },

  exportProductPerformanceAsync(params?: ProductPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/product-performance/export/async', { ...params, format }).then(r => r.data.data)
  },

  userProductPerformanceAsync(params?: UserProductPerformanceFilters): Promise<AsyncJob> {
    return api.post('/reports/user-product-performance/async', params).then(r => r.data.data)
  },

  exportUserProductPerformanceAsync(params?: UserProductPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/user-product-performance/export/async', { ...params, format }).then(r => r.data.data)
  },

  salesAsync(params?: SalesReportFilters): Promise<AsyncJob> {
    return api.post('/reports/sales/async', params).then(r => r.data.data)
  },

  exportSalesAsync(params?: SalesReportFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/sales/export/async', { ...params, format }).then(r => r.data.data)
  },

  // --- Async export (queued file generation) ---
  exportUserPerformanceAsync(params?: UserPerformanceFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/user-performance/export/async', { ...params, format }).then(r => r.data.data)
  },

  exportCompanyAsync(params?: CompanyReportFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/company/export/async', { ...params, format }).then(r => r.data.data)
  },

  exportCustomerAsync(params?: CustomerReportFilters, format: ExportFormat = 'xlsx'): Promise<AsyncJob> {
    return api.post('/reports/customer/export/async', { ...params, format }).then(r => r.data.data)
  },
}
