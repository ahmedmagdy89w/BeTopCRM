import api from './api'
import type { EmailTemplate, GenerateTemplateParams, AsyncJob } from '@/types'
import type { PaginatedResponse } from '@/types'

export const templateService = {
  list(params?: Record<string, unknown>): Promise<PaginatedResponse<EmailTemplate>> {
    return api.get('/email-templates', { params }).then(r => r.data)
  },

  get(id: number): Promise<EmailTemplate> {
    return api.get(`/email-templates/${id}`).then(r => r.data.data)
  },

  create(data: Partial<EmailTemplate>): Promise<EmailTemplate> {
    return api.post('/email-templates', data).then(r => r.data.data)
  },

  update(id: number, data: Partial<EmailTemplate>): Promise<EmailTemplate> {
    return api.put(`/email-templates/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/email-templates/${id}`)
  },

  preview(id: number, sampleData?: Record<string, string>): Promise<{ subject: string; body: string }> {
    return api.post(`/email-templates/${id}/preview`, sampleData).then(r => r.data.data)
  },

  generate(params: GenerateTemplateParams): Promise<AsyncJob> {
    return api.post('/email-templates/generate', params).then(r => r.data.data)
  },
}
