import type { RouteRecordRaw } from 'vue-router'

export const settingsRoutes: RouteRecordRaw[] = [
  {
    path: 'settings',
    name: 'settings',
    component: () => import('@/views/settings/UserSettingsView.vue'),
  },
  {
    path: 'company-settings',
    name: 'company-settings',
    component: () => import('@/views/settings/CompanySettingsView.vue'),
    meta: { permission: 'view-company-settings' },
  },
  {
    path: 'settings/custom-fields',
    name: 'custom-fields.index',
    component: () => import('@/views/settings/CustomFieldGroupsView.vue'),
    meta: { permissions: ['create-custom-fields', 'update-custom-fields', 'delete-custom-fields'] },
  },
]
