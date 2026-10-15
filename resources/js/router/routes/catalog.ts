import type { RouteRecordRaw } from 'vue-router'

export const catalogRoutes: RouteRecordRaw[] = [
  {
    path: 'tags',
    name: 'tags.index',
    component: () => import('@/views/tags/TagListView.vue'),
    meta: { permission: 'create-tags' },
  },
  {
    path: 'products',
    name: 'products.index',
    component: () => import('@/views/products/ProductListView.vue'),
    meta: { permission: 'view-products' },
  },
  {
    path: 'products/create',
    name: 'products.create',
    component: () => import('@/views/products/ProductFormView.vue'),
    meta: { permission: 'create-products' },
  },
  {
    path: 'products/:id',
    name: 'products.show',
    component: () => import('@/views/products/ProductDetailView.vue'),
    meta: { permission: 'view-products' },
  },
  {
    path: 'products/:id/edit',
    name: 'products.edit',
    component: () => import('@/views/products/ProductFormView.vue'),
    meta: { permission: 'update-products' },
  },
  {
    path: 'products/categories',
    name: 'productCategories.index',
    component: () => import('@/views/products/ProductCategoryListView.vue'),
    meta: { permission: 'view-products' },
  },
  {
    path: 'products/taxes',
    name: 'taxes.index',
    component: () => import('@/views/products/TaxListView.vue'),
    meta: { permission: 'view-taxes' },
  },
]
