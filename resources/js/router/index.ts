import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { administrationRoutes } from './routes/administration'
import { authRoutes } from './routes/auth'
import { catalogRoutes } from './routes/catalog'
import { customerRoutes } from './routes/customers'
import { dashboardRoutes } from './routes/dashboard'
import { engagementRoutes } from './routes/engagement'
import { forbiddenRoutes, notFoundRoutes } from './routes/errors'
import { marketingRoutes } from './routes/marketing'
import { opportunityRoutes } from './routes/opportunities'
import { reportRoutes } from './routes/reports'
import { settingsRoutes } from './routes/settings'
import { registerGuards } from './guards'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guest?: boolean
    permission?: string
    permissions?: string[]
  }
}

const routes: RouteRecordRaw[] = [
  // Public
  ...authRoutes,
  // Authenticated
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      ...dashboardRoutes,
      ...customerRoutes,
      ...opportunityRoutes,
      ...engagementRoutes,
      ...reportRoutes,
      ...marketingRoutes,
      ...catalogRoutes,
      ...administrationRoutes,
      ...settingsRoutes,
      // Error pages
      ...forbiddenRoutes,
    ],
  },

  // 404
  ...notFoundRoutes,
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

registerGuards(router)

export default router
