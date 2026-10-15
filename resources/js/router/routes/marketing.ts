import type { RouteRecordRaw } from 'vue-router'

export const marketingRoutes: RouteRecordRaw[] = [
  {
    path: 'campaigns',
    name: 'campaigns.index',
    component: () => import('@/views/campaigns/CampaignListView.vue'),
    meta: { permission: 'view-campaigns' },
  },
  {
    path: 'campaigns/create',
    name: 'campaigns.create',
    component: () => import('@/views/campaigns/CampaignFormView.vue'),
    meta: { permission: 'create-campaigns' },
  },
  {
    path: 'campaigns/:id',
    name: 'campaigns.show',
    component: () => import('@/views/campaigns/CampaignDetailView.vue'),
    meta: { permission: 'view-campaigns' },
  },
  {
    path: 'campaigns/:id/edit',
    name: 'campaigns.edit',
    component: () => import('@/views/campaigns/CampaignFormView.vue'),
    meta: { permission: 'update-campaigns' },
  },
  {
    path: 'email-templates',
    name: 'templates.index',
    component: () => import('@/views/email-templates/TemplateListView.vue'),
    meta: { permission: 'create-templates' },
  },
  {
    path: 'email-templates/create',
    name: 'templates.create',
    component: () => import('@/views/email-templates/TemplateFormView.vue'),
    meta: { permission: 'create-templates' },
  },
  {
    path: 'email-templates/:id/edit',
    name: 'templates.edit',
    component: () => import('@/views/email-templates/TemplateFormView.vue'),
    meta: { permission: 'update-templates' },
  },
]
