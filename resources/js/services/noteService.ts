import api from '@/services/api'
import type { Note, PaginatedResponse } from '@/types'

export const noteService = {
  list(entityType: string, entityId: number, params: Record<string, unknown> = {}): Promise<PaginatedResponse<Note>> {
    return api.get(`/${entityType}/${entityId}/notes`, { params }).then(r => r.data)
  },

  create(entityType: string, entityId: number, data: { body: string; parent_id?: number; is_pinned?: boolean }): Promise<Note> {
    return api.post(`/${entityType}/${entityId}/notes`, data).then(r => r.data.data)
  },

  update(noteId: number, data: { body: string; is_pinned?: boolean }): Promise<Note> {
    return api.put(`/notes/${noteId}`, data).then(r => r.data.data)
  },

  delete(noteId: number): Promise<void> {
    return api.delete(`/notes/${noteId}`)
  },

  togglePin(noteId: number): Promise<Note> {
    return api.post(`/notes/${noteId}/toggle-pin`).then(r => r.data.data)
  },
}
