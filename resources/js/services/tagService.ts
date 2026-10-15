import api from '@/services/api'
import type { Tag } from '@/types'

export const tagService = {
  list(): Promise<Tag[]> {
    return api.get('/tags').then(r => r.data.data ?? r.data)
  },

  create(data: { name: string; color?: string }): Promise<Tag> {
    return api.post('/tags', data).then(r => r.data.data)
  },

  update(id: number, data: { name?: string; color?: string }): Promise<Tag> {
    return api.put(`/tags/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/tags/${id}`)
  },
}
