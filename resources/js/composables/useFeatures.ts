import { computed } from 'vue'
import { useCompanySettingsStore } from '@/stores/companySettings'

/**
 * Company feature flags for optional sales modules.
 * Missing settings default to enabled so existing installs keep current behavior.
 */
export function useFeatures() {
  const store = useCompanySettingsStore()

  const showQuotations = computed(() => store.showQuotations())
  const showInvoices = computed(() => store.showInvoices())
  const showSalesDocuments = computed(() => showQuotations.value || showInvoices.value)

  return { showQuotations, showInvoices, showSalesDocuments }
}
