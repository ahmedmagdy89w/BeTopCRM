import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermission() {
  const auth = useAuthStore()

  function can(permission: string): boolean {
    return auth.hasPermission(permission)
  }

  function canAny(...permissions: string[]): boolean {
    return permissions.some(p => auth.hasPermission(p))
  }

  const isAdmin = computed(() => auth.isAdmin)
  const isManager = computed(() => auth.isManager)

  return {
    can,
    canAny,
    isAdmin,
    isManager,
  }
}
