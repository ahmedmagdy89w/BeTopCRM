import api from './api'
import type { Opportunity, Product } from '@/types'
import type { PaginatedResponse } from '@/types/api'

export interface ProductFilters {
  page?: number
  per_page?: number
  search?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  category_id?: number
  is_active?: boolean
}

export const productService = {
  list(params?: ProductFilters): Promise<PaginatedResponse<Product>> {
    return api.get('/products', { params }).then(r => r.data)
  },
  get(id: number): Promise<Product> {
    return api.get(`/products/${id}`).then(r => r.data.data)
  },
  opportunities(id: number): Promise<Opportunity[]> {
    return api.get(`/products/${id}/opportunities`).then(r => r.data.data)
  },
  create(data: Partial<Product>): Promise<Product> {
    return api.post('/products', data).then(r => r.data.data)
  },
  update(id: number, data: Partial<Product>): Promise<Product> {
    return api.put(`/products/${id}`, data).then(r => r.data.data)
  },
  delete(id: number): Promise<void> {
    return api.delete(`/products/${id}`)
  },
}
