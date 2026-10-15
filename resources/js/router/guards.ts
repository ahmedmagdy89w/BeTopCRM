import type { Router } from 'vue-router'

export function registerGuards(router: Router): void {
  router.beforeEach(async (to) => {
    const { useAuthStore } = await import('@/stores/auth')
    const auth = useAuthStore()

    // Rehydrate session on hard reload (cookie present but user not yet fetched)
    if (!auth.initialized && !auth.user) {
      await auth.initFromStorage()
    }

    // Redirect authenticated users away from guest pages
    if (to.meta.guest && auth.isAuthenticated) {
      return { name: 'dashboard' }
    }

    // Require auth
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    const requiredPermission = to.meta.permission
    const requiredPermissions = to.meta.permissions
    const hasRequiredPermission = requiredPermission
      ? auth.hasPermission(requiredPermission)
      : requiredPermissions?.length
        ? requiredPermissions.some(permission => auth.hasPermission(permission))
        : true

    if ((requiredPermission || requiredPermissions?.length) && !hasRequiredPermission) {
      return { name: 'forbidden' }
    }

    if (to.name === 'salesDocuments') {
      const { useCompanySettingsStore } = await import('@/stores/companySettings')
      const companySettings = useCompanySettingsStore()
      if (!companySettings.loaded) {
        await companySettings.fetchSettings()
      }
      if (!companySettings.showQuotations() && !companySettings.showInvoices()) {
        return { name: 'forbidden' }
      }
    }
  })
}
