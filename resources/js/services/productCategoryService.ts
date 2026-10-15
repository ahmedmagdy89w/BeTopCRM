import api from './api'
import type { ProductCategory } from '@/types'

export const productCategoryService = {
  list(): Promise<ProductCategory[]> {
    return api.get('/product-categories').then(r => r.data.data)
  },
  tree(): Promise<ProductCategory[]> {
    return api.get('/product-categories/tree').then(r => r.data.data)
  },
  create(data: Partial<ProductCategory>): Promise<ProductCategory> {
    return api.post('/product-categories', data).then(r => r.data.data)
  },
  update(id: number, data: Partial<ProductCategory>): Promise<ProductCategory> {
    return api.put(`/product-categories/${id}`, data).then(r => r.data.data)
  },
  delete(id: number): Promise<void> {
    return api.delete(`/product-categories/${id}`)
  },
  reorder(data: { categories: { id: number; sort_order: number }[] }): Promise<void> {
    return api.post('/product-categories/reorder', data)
  },
}
