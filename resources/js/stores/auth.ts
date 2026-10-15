import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types'
import { authService } from '@/services/authService'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const permissions = computed(() => user.value?.permissions ?? [])
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isManager = computed(() => user.value?.role === 'manager')
  const isSalesRep = computed(() => user.value?.role === 'sales_rep')

  function hasPermission(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function hasRole(role: string): boolean {
    return user.value?.role === role
  }

  async function login(credentials: { email: string; password: string; remember?: boolean }) {
    // 1. Initialize CSRF protection
    await authService.getCsrfCookie()
    // 2. Authenticate session (browser receives HttpOnly cookie)
    const response = await authService.login(credentials)
    user.value = response.user
    // No token written to localStorage!
    initialized.value = true
  }

  async function logout() {
    try {
      await authService.logout()
    } finally {
      clearSession()
    }
  }

  async function fetchMe() {
    user.value = await authService.me()
    initialized.value = true
  }

  function clearSession() {
    user.value = null
    initialized.value = true
    localStorage.removeItem('crm_token') // clean up any legacy keys
  }

  async function initFromStorage() {
    if (initialized.value) {
      return
    }
    try {
      // Direct check against backend session
      user.value = await authService.me()
    } catch {
      clearSession()
      return
    }
    initialized.value = true
  }

  return {
    user,
    initialized,
    isAuthenticated,
    permissions,
    isAdmin,
    isManager,
    isSalesRep,
    hasPermission,
    hasRole,
    login,
    logout,
    fetchMe,
    clearSession,
    initFromStorage,
  }
})
