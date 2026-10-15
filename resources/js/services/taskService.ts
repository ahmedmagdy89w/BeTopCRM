import api from '@/services/api'
import type { Task, PaginatedResponse } from '@/types'

export interface TaskFilters {
  page?: number
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  search?: string
  user_id?: number
  status?: string
  priority?: string
  entity_type?: string
  entity_id?: number
  overdue?: boolean
  due_today?: boolean
}

export interface TaskSummary {
  overdue: number
  due_today: number
  upcoming_7_days: number
  in_progress: number
  total_pending: number
}

export const taskService = {
  list(params: TaskFilters = {}): Promise<PaginatedResponse<Task>> {
    return api.get('/tasks', { params }).then(r => r.data)
  },

  get(id: number): Promise<Task> {
    return api.get(`/tasks/${id}`).then(r => r.data.data)
  },

  create(data: Partial<Task> & { entity_type?: string; entity_id?: number }): Promise<Task> {
    return api.post('/tasks', data).then(r => r.data.data)
  },

  update(id: number, data: Partial<Task>): Promise<Task> {
    return api.put(`/tasks/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/tasks/${id}`)
  },

  complete(id: number): Promise<Task> {
    return api.patch(`/tasks/${id}/complete`).then(r => r.data.data)
  },

  mySummary(): Promise<TaskSummary> {
    return api.get('/tasks/my-summary').then(r => r.data)
  },
}
