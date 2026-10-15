import type { RouteRecordRaw } from 'vue-router'

// Opportunities — Kanban board + table + form
export const opportunityRoutes: RouteRecordRaw[] = [
  {
    path: 'opportunities',
    name: 'opportunities.index',
    component: () => import('@/views/opportunities/OpportunityBoardView.vue'),
    meta: { permission: 'view-opportunities' },
  },
  {
    path: 'opportunities/table',
    name: 'opportunities.table',
    component: () => import('@/views/opportunities/OpportunityListView.vue'),
    meta: { permission: 'view-opportunities' },
  },
  {
    path: 'sales-documents',
    name: 'salesDocuments',
    component: () => import('@/views/opportunities/SalesDocumentsView.vue'),
    meta: { permissions: ['view-quotations', 'view-invoices'] },
  },
  {
    path: 'opportunities/create',
    name: 'opportunities.createGlobal',
    component: () => import('@/views/opportunities/OpportunityFormView.vue'),
    meta: { permission: 'create-opportunities' },
  },
  {
    path: 'customers/:customerId/opportunities/create',
    name: 'opportunities.create',
    component: () => import('@/views/opportunities/OpportunityFormView.vue'),
    meta: { permission: 'create-opportunities' },
  },
  {
    path: 'customers/:customerId/opportunities/:id',
    name: 'opportunities.show',
    component: () => import('@/views/opportunities/OpportunityDetailView.vue'),
    meta: { permission: 'view-opportunities' },
  },
  {
    path: 'customers/:customerId/opportunities/:id/edit',
    name: 'opportunities.edit',
    component: () => import('@/views/opportunities/OpportunityFormView.vue'),
    meta: { permission: 'update-opportunities' },
  },
]
