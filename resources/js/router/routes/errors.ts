import type { RouteRecordRaw } from 'vue-router'

// Rendered inside the authenticated layout
export const forbiddenRoutes: RouteRecordRaw[] = [
  {
    path: 'forbidden',
    name: 'forbidden',
    component: () => import('@/views/errors/ForbiddenView.vue'),
  },
]

// Top-level catch-all, must stay last
export const notFoundRoutes: RouteRecordRaw[] = [
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/errors/NotFoundView.vue'),
  },
]
