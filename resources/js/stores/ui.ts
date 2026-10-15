import { defineStore } from 'pinia'
import { ref } from 'vue'
import { i18n } from '@/i18n'

export const useUIStore = defineStore('ui', () => {
  const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')
  const locale = ref<'en' | 'ar'>((localStorage.getItem('locale') as 'en' | 'ar') || 'en')
  const dateFormat = ref('yyyy-MM-dd')
  const timezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone)

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('sidebar_collapsed', String(sidebarCollapsed.value))
  }

  function setSidebarCollapsed(val: boolean) {
    sidebarCollapsed.value = val
    localStorage.setItem('sidebar_collapsed', String(val))
  }

  function setLocale(newLocale: 'en' | 'ar') {
    locale.value = newLocale
    localStorage.setItem('locale', newLocale)
    i18n.global.locale.value = newLocale
    document.documentElement.dir = newLocale === 'ar' ? 'rtl' : 'ltr'
    document.documentElement.lang = newLocale
  }

  return {
    sidebarCollapsed,
    locale,
    dateFormat,
    timezone,
    toggleSidebar,
    setSidebarCollapsed,
    setLocale,
  }
})
