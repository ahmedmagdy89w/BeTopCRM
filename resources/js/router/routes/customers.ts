import type { RouteRecordRaw } from 'vue-router'

// Customers (unified individual|company, is_lead flag)
export const customerRoutes: RouteRecordRaw[] = [
  {
    path: 'customers',
    name: 'customers.index',
    component: () => import('@/views/customers/CustomerListView.vue'),
    meta: { permission: 'view-customers' },
  },
  {
    path: 'customers/create',
    name: 'customers.create',
    component: () => import('@/views/customers/CustomerFormView.vue'),
    meta: { permission: 'create-customers' },
  },
  {
    path: 'customers/:id',
    name: 'customers.show',
    component: () => import('@/views/customers/CustomerDetailView.vue'),
    meta: { permission: 'view-customers' },
  },
  {
    path: 'customers/:id/edit',
    name: 'customers.edit',
    component: () => import('@/views/customers/CustomerFormView.vue'),
    meta: { permission: 'update-customers' },
  },
]
