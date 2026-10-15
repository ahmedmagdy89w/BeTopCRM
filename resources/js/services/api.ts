import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
  withCredentials: true, // Automatically sends & receives HttpOnly session cookies + CSRF tokens
})

api.interceptors.request.use(async (config) => {
  // Attach current locale to Accept-Language
  const { useUIStore } = await import('@/stores/ui')
  const ui = useUIStore()
  if (ui.locale) {
    config.headers['Accept-Language'] = ui.locale
  }

  // NOTE: No manual Bearer token attachment from localStorage!
  // The browser automatically attaches the secure HttpOnly laravel_session cookie.
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      const { useAuthStore } = await import('@/stores/auth')
      const auth = useAuthStore()
      auth.clearSession()

      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' })
      }
    }

    if (error.response?.status === 403) {
      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'forbidden') {
        router.push({ name: 'forbidden' })
      }
    }
    return Promise.reject(error)
  },
)

export default api
