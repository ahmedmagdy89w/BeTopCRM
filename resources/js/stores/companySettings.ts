import { ref } from 'vue'
import { defineStore } from 'pinia'
import { companySettingsService, type CompanySettings, type CompanySettingsPayload } from '@/services/companySettingsService'

function emptyLabels(): CompanySettings['product_labels'] {
  return { singular_en: null, plural_en: null, singular_ar: null, plural_ar: null }
}

export const useCompanySettingsStore = defineStore('companySettings', () => {
  const settings = ref<CompanySettings | null>(null)
  const loading = ref(false)
  const loaded = ref(false)

  async function fetchSettings(force = false) {
    if ((loaded.value && !force) || loading.value) return
    loading.value = true
    try {
      settings.value = await companySettingsService.get()
      loaded.value = true
    } catch {
      // Labels fall back to built-in i18n strings when settings are unreachable.
    } finally {
      loading.value = false
    }
  }

  function setSettings(data: CompanySettings) {
    settings.value = data
    loaded.value = true
  }

  async function updateSettings(data: Partial<CompanySettingsPayload>) {
    const updated = await companySettingsService.update(data)
    setSettings(updated)
    return updated
  }

  function productLabels() {
    return settings.value?.product_labels ?? emptyLabels()
  }

  function showQuotations(): boolean {
    return settings.value?.features?.quotations_enabled ?? true
  }

  function showInvoices(): boolean {
    return settings.value?.features?.invoices_enabled ?? true
  }

  return { settings, loading, loaded, fetchSettings, setSettings, updateSettings, productLabels, showQuotations, showInvoices }
})
