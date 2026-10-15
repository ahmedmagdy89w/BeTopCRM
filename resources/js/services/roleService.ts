import api from './api'
import type { Role } from '@/types'

export interface RolePayload {
  name: string
  permissions: string[]
}

export const roleService = {
  list(): Promise<Role[]> {
    return api.get('/roles').then(r => r.data.data)
  },

  get(id: number): Promise<Role> {
    return api.get(`/roles/${id}`).then(r => r.data.data)
  },

  create(data: RolePayload): Promise<Role> {
    return api.post('/roles', data).then(r => r.data.data)
  },

  update(id: number, data: RolePayload): Promise<Role> {
    return api.put(`/roles/${id}`, data).then(r => r.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/roles/${id}`)
  },

  listPermissions(): Promise<string[]> {
    return api.get('/permissions').then(r => r.data.data)
  },
}
