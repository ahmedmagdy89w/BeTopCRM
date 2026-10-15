import type { RouteRecordRaw } from 'vue-router'

export const reportRoutes: RouteRecordRaw[] = [
  {
    path: 'reports/user-performance',
    name: 'reports.userPerformance',
    component: () => import('@/views/reports/UserPerformanceReport.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/company',
    name: 'reports.company',
    component: () => import('@/views/reports/CompanyReport.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/customer',
    name: 'reports.customer',
    component: () => import('@/views/reports/CustomerReport.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/lead-pipeline',
    name: 'reports.leadPipeline',
    component: () => import('@/views/reports/LeadPipelineReportView.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/product-performance',
    name: 'reports.productPerformance',
    component: () => import('@/views/reports/ProductPerformanceReportView.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/user-product-performance',
    name: 'reports.userProductPerformance',
    component: () => import('@/views/reports/UserProductPerformanceView.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'reports/sales',
    name: 'reports.sales',
    component: () => import('@/views/reports/SalesReportView.vue'),
    meta: { permission: 'view-reports' },
  },
  {
    path: 'ai-assistant',
    name: 'aiAssistant',
    component: () => import('@/views/ai/AiChatView.vue'),
    meta: { permission: 'create-ai-chats' },
  },
]
