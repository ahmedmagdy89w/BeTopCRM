import type { RouteRecordRaw } from 'vue-router'

export const administrationRoutes: RouteRecordRaw[] = [
  {
    path: 'webhooks',
    name: 'webhooks.index',
    component: () => import('@/views/webhooks/WebhookListView.vue'),
    meta: { permission: 'view-webhooks' },
  },
  {
    path: 'webhooks/create',
    name: 'webhooks.create',
    component: () => import('@/views/webhooks/WebhookFormView.vue'),
    meta: { permission: 'create-webhooks' },
  },
  {
    path: 'webhooks/:id/edit',
    name: 'webhooks.edit',
    component: () => import('@/views/webhooks/WebhookFormView.vue'),
    meta: { permission: 'update-webhooks' },
  },
  {
    path: 'audit-logs',
    name: 'auditLogs.index',
    component: () => import('@/views/audit-logs/AuditLogView.vue'),
    meta: { permission: 'view-audit-log' },
  },
  {
    path: 'import-export',
    name: 'importExport',
    component: () => import('@/views/import-export/ImportExportView.vue'),
    meta: { permissions: ['import-data', 'export-data'] },
  },
  {
    path: 'users',
    name: 'users.index',
    component: () => import('@/views/users/UserListView.vue'),
    meta: { permission: 'view-users' },
  },
  {
    path: 'users/create',
    name: 'users.create',
    component: () => import('@/views/users/UserFormView.vue'),
    meta: { permission: 'create-users' },
  },
  {
    path: 'users/:id/edit',
    name: 'users.edit',
    component: () => import('@/views/users/UserFormView.vue'),
    meta: { permission: 'update-users' },
  },
  {
    path: 'roles',
    name: 'roles.index',
    component: () => import('@/views/users/RoleListView.vue'),
    meta: { permission: 'view-users' },
  },
  {
    path: 'roles/create',
    name: 'roles.create',
    component: () => import('@/views/users/RoleFormView.vue'),
    meta: { permission: 'create-users' },
  },
  {
    path: 'roles/:id/edit',
    name: 'roles.edit',
    component: () => import('@/views/users/RoleFormView.vue'),
    meta: { permission: 'update-users' },
  },
]
