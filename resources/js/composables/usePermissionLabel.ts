import { useI18n } from 'vue-i18n'
import { useCompanySettingsStore } from '@/stores/companySettings'

export function usePermissionLabel() {
  const { t, te, locale } = useI18n()

  function permissionLabel(permission: string): string {
    const key = `roles.permissionLabels.${permission}`
    return te(key) ? t(key) : permission
  }

  function moduleLabel(module: string): string {
    if (module === 'products') {
      const labels = useCompanySettingsStore().productLabels()
      const custom = locale.value === 'ar' ? labels.plural_ar : labels.plural_en
      if (custom?.trim()) return custom.trim()
    }
    const key = `roles.moduleLabels.${module}`
    return te(key) ? t(key) : module
  }

  return {
    permissionLabel,
    moduleLabel,
  }
}
