import api from './api'

export interface CompanySettingsAddress {
  street: string | null
  city: string | null
  state: string | null
  country: string | null
  zip: string | null
}

export interface CompanySettingsAi {
  provider: string | null
  model: string | null
  base_url: string | null
  api_key_set: boolean
  api_key_masked: string | null
}

export interface CompanySettingsAiPayload {
  provider: string | null
  model: string | null
  base_url: string | null
  api_key?: string | null
}

export interface CompanySettingsProductLabels {
  singular_en: string | null
  plural_en: string | null
  singular_ar: string | null
  plural_ar: string | null
}

export interface CompanySettingsFeatures {
  quotations_enabled: boolean
  invoices_enabled: boolean
}

export interface CompanySettings {
  company_name: string | null
  company_email: string | null
  company_phone: string | null
  company_website: string | null
  company_tax_number: string | null
  company_currency: string | null
  company_timezone: string | null
  address: CompanySettingsAddress
  logo_url: string | null
  ai: CompanySettingsAi
  product_labels: CompanySettingsProductLabels
  features: CompanySettingsFeatures
}

export type CompanySettingsPayload = Omit<CompanySettings, 'logo_url' | 'ai' | 'product_labels' | 'features'> & {
  ai?: CompanySettingsAiPayload
  product_labels?: Partial<CompanySettingsProductLabels>
  features?: Partial<CompanySettingsFeatures>
}

export const companySettingsService = {
  get(): Promise<CompanySettings> {
    return api.get('/company/settings').then(r => r.data.data)
  },

  revealAiApiKey(): Promise<string | null> {
    return api.post('/company/settings/ai-key/reveal', {}).then(r => r.data.data.api_key)
  },

  update(data: Partial<CompanySettingsPayload>): Promise<CompanySettings> {
    return api.put('/company/settings', data).then(r => r.data.data)
  },

  uploadLogo(file: File): Promise<CompanySettings> {
    const formData = new FormData()
    formData.append('logo', file)
    return api.post('/company/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data)
  },

  deleteLogo(): Promise<void> {
    return api.delete('/company/settings/logo')
  },
}
