import api from '@/services/api'
import type { Attachment } from '@/types'

export const attachmentService = {
  list(entityType: string, entityId: number): Promise<Attachment[]> {
    return api.get(`/${entityType}/${entityId}/attachments`).then(r => r.data.data ?? r.data)
  },

  upload(entityType: string, entityId: number, files: File[]): Promise<Attachment[]> {
    const formData = new FormData()
    files.forEach(file => formData.append('files[]', file))
    return api.post(`/${entityType}/${entityId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data ?? r.data)
  },

  download(attachmentId: number): string {
    return `${api.defaults.baseURL}/attachments/${attachmentId}/download`
  },

  delete(attachmentId: number): Promise<void> {
    return api.delete(`/attachments/${attachmentId}`)
  },
}
