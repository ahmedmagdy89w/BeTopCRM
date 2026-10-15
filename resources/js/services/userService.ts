import api from './api'
import type { User } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface UserFilters {
  page?: number
  per_page?: number
  search?: string
  role?: string
  is_active?: boolean | string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
}

export interface AssignableUser {
  id: number
  name: string
  email: string
  avatar_url?: string | null
}

export interface AssignableUserFilters {
  search?: string
  per_page?: number
}

export const userService = {
  list(params?: UserFilters): Promise<PaginatedResponse<User>> {
    return api.get('/users', { params }).then(r => r.data)
  },
  assignable(params?: AssignableUserFilters): Promise<PaginatedResponse<AssignableUser>> {
    return api.get('/users/assignable', { params }).then(r => r.data)
  },
  getAssignable(id: number): Promise<AssignableUser> {
    return api.get(`/users/assignable/${id}`).then(r => r.data.data)
  },
  get(id: number): Promise<User> {
    return api.get(`/users/${id}`).then(r => r.data.data)
  },
  create(data: Record<string, unknown>): Promise<User> {
    return api.post('/users', data).then(r => r.data.data)
  },
  update(id: number, data: Record<string, unknown>): Promise<User> {
    return api.put(`/users/${id}`, data).then(r => r.data.data)
  },
  delete(id: number): Promise<void> {
    return api.delete(`/users/${id}`)
  },
  updateRole(id: number, role: string): Promise<User> {
    return api.put(`/users/${id}/role`, { role }).then(r => r.data.data)
  },
  activate(id: number): Promise<User> {
    return api.post(`/users/${id}/activate`).then(r => r.data.data)
  },
}
