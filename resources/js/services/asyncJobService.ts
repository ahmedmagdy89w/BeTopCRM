import api from './api'
import type { AsyncJob, PaginatedResponse } from '@/types'

export interface AsyncJobFilters {
  type?: string
  status?: string
  page?: number
  per_page?: number
}

export const asyncJobService = {
  list(params?: AsyncJobFilters): Promise<PaginatedResponse<AsyncJob>> {
    return api.get('/async-jobs', { params }).then(r => r.data)
  },

  get(id: number): Promise<AsyncJob> {
    return api.get(`/async-jobs/${id}`).then(r => r.data.data)
  },

  async download(id: number): Promise<void> {
    const response = await api.get(`/async-jobs/${id}/download`, { responseType: 'blob' })
    const disposition: string = response.headers['content-disposition'] ?? ''
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    const filename = match?.[1]?.replace(/['"]/g, '') ?? `export-${id}`
    const url = URL.createObjectURL(response.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  },
}
