import api from './api'

export interface Tax {
  id: number
  name: string
  percent: string
  is_active: boolean
  products_count?: number
  created_at: string
}

export const taxService = {
  list(activeOnly = false): Promise<Tax[]> {
    return api.get('/taxes', { params: activeOnly ? { active_only: 1 } : {} }).then(r => r.data.data ?? r.data)
  },

  get(id: number): Promise<Tax> {
    return api.get(`/taxes/${id}`).then(r => r.data.data)
  },

  create(data: { name: string; percent: number | string; is_active?: boolean }): Promise<Tax> {
    return api.post('/taxes', data).then(r => r.data.data)
  },

  update(id: number, data: { name?: string; percent?: number | string; is_active?: boolean }): Promise<Tax> {
    return api.put(`/taxes/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/taxes/${id}`)
  },
}
