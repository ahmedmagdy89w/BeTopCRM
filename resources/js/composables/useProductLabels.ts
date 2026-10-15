import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanySettingsStore } from '@/stores/companySettings'

/**
 * Company-configurable "Product" terminology.
 *
 * Returns the custom singular/plural labels for the active locale when an
 * admin has set them in Company Settings, otherwise the built-in i18n
 * strings. Settings are fetched once and cached in the Pinia store.
 */
export function useProductLabels() {
  const { locale, t } = useI18n()
  const store = useCompanySettingsStore()

  onMounted(() => {
    void store.fetchSettings()
  })

  const labels = computed(() => store.productLabels())

  const singular = computed(() => {
    const custom = locale.value === 'ar' ? labels.value.singular_ar : labels.value.singular_en
    return custom?.trim() ? custom.trim() : t('products.singular')
  })

  const plural = computed(() => {
    const custom = locale.value === 'ar' ? labels.value.plural_ar : labels.value.plural_en
    return custom?.trim() ? custom.trim() : t('products.title')
  })

  const hasCustom = computed(() => {
    const custom = locale.value === 'ar' ? labels.value.plural_ar : labels.value.plural_en
    return !!custom?.trim()
  })

  const productPerformanceTitle = computed(() => hasCustom.value
    ? t('reports.productPerformanceCustom', { label: plural.value })
    : t('reports.productPerformance'))

  return { singular, plural, hasCustom, productPerformanceTitle }
}
