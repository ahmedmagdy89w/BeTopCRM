import type { RouteRecordRaw } from 'vue-router'

// Activities (global list across viewable customers) + Tasks
export const engagementRoutes: RouteRecordRaw[] = [
  {
    path: 'activities',
    name: 'activities.index',
    component: () => import('@/views/activities/ActivityListView.vue'),
    meta: { permission: 'view-activities' },
  },
  {
    path: 'tasks',
    name: 'tasks.index',
    component: () => import('@/views/tasks/TaskListView.vue'),
    meta: { permission: 'view-tasks' },
  },
  {
    path: 'tasks/create',
    name: 'tasks.create',
    component: () => import('@/views/tasks/TaskFormView.vue'),
    meta: { permission: 'create-tasks' },
  },
  {
    path: 'tasks/:id/edit',
    name: 'tasks.edit',
    component: () => import('@/views/tasks/TaskFormView.vue'),
    meta: { permission: 'update-tasks' },
  },
]
