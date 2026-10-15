import api from './api'
import type { Import } from '@/types'
import type { PaginatedResponse } from '@/types/api'

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

export const importExportService = {
  // Import
  listImports(): Promise<PaginatedResponse<Import>> {
    return api.get('/import').then(r => r.data)
  },
  getImport(id: number): Promise<Import> {
    return api.get(`/import/${id}`).then(r => r.data.data)
  },
  startImport(entityType: string, file: File): Promise<Import> {
    const formData = new FormData()
    formData.append('file', file)
    return api.post(`/import/${entityType}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data)
  },
  templateUrl(entityType: string): string {
    return `${API_BASE}/import/${entityType}/template`
  },

  // Export
  exportUrl(entityType: string, params?: Record<string, string>): string {
    const query = params ? '?' + new URLSearchParams(params).toString() : ''
    return `${API_BASE}/export/${entityType}${query}`
  },
}
