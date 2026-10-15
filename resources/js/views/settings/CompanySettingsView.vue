<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { companySettingsService } from '@/services/companySettingsService'
import { useCompanySettingsStore } from '@/stores/companySettings'
import { CURRENCIES } from '@/utils/currencies'
import type { CompanySettings } from '@/services/companySettingsService'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const { can } = usePermission()
const toast = useToast()

const settings = ref<CompanySettings | null>(null)
const loading = ref(true)
const saving = ref(false)
const aiSaving = ref(false)
const terminologySaving = ref(false)
const featuresSaving = ref(false)
const revealingAiKey = ref(false)
const aiKeyInput = ref('')
const showAiKey = ref(false)
const errors = ref<Record<string, string[]>>({})
const logoSaving = ref(false)

const canEdit = computed(() => can('update-company-settings'))
const currencies = CURRENCIES

const aiProviders = [
  { value: 'gemini', label: 'Gemini', model: 'gemini-2.0-flash' },
  { value: 'openai', label: 'OpenAI', model: 'gpt-4o' },
  { value: 'anthropic', label: 'Anthropic', model: 'claude-3-5-sonnet-latest' },
  { value: 'ollama', label: 'Ollama (local)', model: 'gemma3:4b' },
  { value: 'groq', label: 'Groq', model: 'llama-3.3-70b-versatile' },
  { value: 'deepseek', label: 'DeepSeek', model: 'deepseek-chat' },
  { value: 'mistral', label: 'Mistral', model: 'mistral-large-latest' },
  { value: 'openrouter', label: 'OpenRouter', model: 'openai/gpt-4o' },
  { value: 'xai', label: 'xAI', model: 'grok-beta' },
  { value: 'azure', label: 'Azure OpenAI', model: 'gpt-4o' },
]

const aiModelPlaceholder = computed(() => {
  const found = aiProviders.find(p => p.value === settings.value?.ai.provider)
  return found?.model ?? 'gemini-2.0-flash'
})

function normalizeAi() {
  if (settings.value && !settings.value.ai) {
    settings.value.ai = { provider: null, model: null, base_url: null, api_key_set: false, api_key_masked: null }
  }
}

function normalizeProductLabels() {
  if (settings.value && !settings.value.product_labels) {
    settings.value.product_labels = { singular_en: null, plural_en: null, singular_ar: null, plural_ar: null }
  }
}

function normalizeFeatures() {
  if (settings.value && !settings.value.features) {
    settings.value.features = { quotations_enabled: true, invoices_enabled: true }
  }
}

async function fetch() {
  loading.value = true
  try {
    settings.value = await companySettingsService.get()
    normalizeAi()
    normalizeProductLabels()
    normalizeFeatures()
    useCompanySettingsStore().setSettings(settings.value)
  } finally {
    loading.value = false
  }
}

async function saveInfo() {
  if (!settings.value) return
  errors.value = {}
  saving.value = true
  try {
    const { logo_url, ...data } = settings.value
    settings.value = await companySettingsService.update(data)
    normalizeProductLabels()
    useCompanySettingsStore().setSettings(settings.value)
    toast.success(t('companySettings.settingsSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    saving.value = false
  }
}

async function saveAi() {
  if (!settings.value) return
  errors.value = {}
  aiSaving.value = true
  try {
    const ai = settings.value.ai
    settings.value = await companySettingsService.update({
      ai: {
        provider: ai.provider,
        model: ai.model,
        base_url: ai.base_url,
        ...(aiKeyInput.value.trim() !== '' ? { api_key: aiKeyInput.value } : {}),
      },
    })
    normalizeAi()
    aiKeyInput.value = ''
    toast.success(t('companySettings.settingsSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    aiSaving.value = false
  }
}

async function saveTerminology() {
  if (!settings.value) return
  errors.value = {}
  terminologySaving.value = true
  try {
    settings.value = await companySettingsService.update({
      product_labels: { ...settings.value.product_labels },
    })
    normalizeProductLabels()
    useCompanySettingsStore().setSettings(settings.value)
    toast.success(t('companySettings.settingsSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    terminologySaving.value = false
  }
}

async function saveFeatures() {
  if (!settings.value) return
  errors.value = {}
  featuresSaving.value = true
  try {
    settings.value = await companySettingsService.update({
      features: { ...settings.value.features },
    })
    normalizeFeatures()
    useCompanySettingsStore().setSettings(settings.value)
    toast.success(t('companySettings.settingsSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    featuresSaving.value = false
  }
}

async function toggleAiKeyVisibility() {
  if (showAiKey.value) {
    showAiKey.value = false
    return
  }

  if (aiKeyInput.value === '' && settings.value?.ai.api_key_set) {
    revealingAiKey.value = true
    try {
      const key = await companySettingsService.revealAiApiKey()
      if (!key) {
        toast.error(t('common.somethingWentWrong'))
        return
      }

      aiKeyInput.value = key
    } catch {
      toast.error(t('common.somethingWentWrong'))
      return
    } finally {
      revealingAiKey.value = false
    }
  }

  showAiKey.value = true
}

async function handleLogoUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.item(0)
  if (!file) return
  logoSaving.value = true
  try {
    settings.value = await companySettingsService.uploadLogo(file)
    toast.success(t('companySettings.logoUploaded'))
  } catch {
    toast.error(t('common.somethingWentWrong'))
  } finally {
    logoSaving.value = false
    input.value = ''
  }
}

async function removeLogo() {
  if (!settings.value) return
  logoSaving.value = true
  try {
    await companySettingsService.deleteLogo()
    settings.value = { ...settings.value, logo_url: null }
    toast.success(t('companySettings.logoRemoved'))
  } catch {
    toast.error(t('common.somethingWentWrong'))
  } finally {
    logoSaving.value = false
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <h2 class="mb-3">{{ t('companySettings.title') }}</h2>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <template v-else-if="settings">
      <!-- Company Information -->
      <BaseCard :title="t('companySettings.companyInfo')" icon="bi-building" class="mb-3">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.companyName') }}</label>
            <input
              v-model="settings.company_name"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.company_name }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_name" class="invalid-feedback">{{ errors.company_name[0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.companyEmail') }}</label>
            <input
              v-model="settings.company_email"
              type="email"
              class="form-control"
              :class="{ 'is-invalid': errors.company_email }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_email" class="invalid-feedback">{{ errors.company_email[0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.companyPhone') }}</label>
            <input
              v-model="settings.company_phone"
              type="tel"
              class="form-control"
              :class="{ 'is-invalid': errors.company_phone }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_phone" class="invalid-feedback">{{ errors.company_phone[0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.companyWebsite') }}</label>
            <input
              v-model="settings.company_website"
              type="url"
              class="form-control"
              :class="{ 'is-invalid': errors.company_website }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_website" class="invalid-feedback">{{ errors.company_website[0] }}</div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('companySettings.taxNumber') }}</label>
            <input
              v-model="settings.company_tax_number"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.company_tax_number }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_tax_number" class="invalid-feedback">{{ errors.company_tax_number[0] }}</div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('companySettings.currency') }}</label>
            <select
              v-model="settings.company_currency"
              class="form-select"
              :class="{ 'is-invalid': errors.company_currency }"
              :disabled="!canEdit || saving"
            >
              <option :value="null">—</option>
              <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
            </select>
            <div v-if="errors.company_currency" class="invalid-feedback">{{ errors.company_currency[0] }}</div>
            <small v-else class="text-muted d-block mt-1">{{ t('companySettings.currencyHint') }}</small>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('companySettings.timezone') }}</label>
            <input
              v-model="settings.company_timezone"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.company_timezone }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors.company_timezone" class="invalid-feedback">{{ errors.company_timezone[0] }}</div>
          </div>
        </div>

        <BaseButton v-if="canEdit" variant="primary" icon="bi-check-lg" :loading="saving" @click="saveInfo">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Address -->
      <BaseCard :title="t('companySettings.address')" icon="bi-geo-alt" class="mb-3">
        <div class="row">
          <div class="col-12 mb-3">
            <label class="form-label">{{ t('companySettings.street') }}</label>
            <input
              v-model="settings.address.street"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['address.street'] }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors['address.street']" class="invalid-feedback">{{ errors['address.street'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.city') }}</label>
            <input
              v-model="settings.address.city"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['address.city'] }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors['address.city']" class="invalid-feedback">{{ errors['address.city'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.state') }}</label>
            <input
              v-model="settings.address.state"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['address.state'] }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors['address.state']" class="invalid-feedback">{{ errors['address.state'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.country') }}</label>
            <input
              v-model="settings.address.country"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['address.country'] }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors['address.country']" class="invalid-feedback">{{ errors['address.country'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.zip') }}</label>
            <input
              v-model="settings.address.zip"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['address.zip'] }"
              :disabled="!canEdit || saving"
            >
            <div v-if="errors['address.zip']" class="invalid-feedback">{{ errors['address.zip'][0] }}</div>
          </div>
        </div>

        <BaseButton v-if="canEdit" variant="primary" icon="bi-check-lg" :loading="saving" @click="saveInfo">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Terminology -->
      <BaseCard :title="t('companySettings.terminologyTitle')" icon="bi-translate" class="mb-3">
        <p class="text-muted small">{{ t('companySettings.terminologyDesc') }}</p>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.singularEn') }}</label>
            <input
              v-model="settings.product_labels.singular_en"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['product_labels.singular_en'] }"
              :disabled="!canEdit || terminologySaving"
            >
            <div v-if="errors['product_labels.singular_en']" class="invalid-feedback">{{ errors['product_labels.singular_en'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.pluralEn') }}</label>
            <input
              v-model="settings.product_labels.plural_en"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['product_labels.plural_en'] }"
              :disabled="!canEdit || terminologySaving"
            >
            <div v-if="errors['product_labels.plural_en']" class="invalid-feedback">{{ errors['product_labels.plural_en'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.singularAr') }}</label>
            <input
              v-model="settings.product_labels.singular_ar"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['product_labels.singular_ar'] }"
              :disabled="!canEdit || terminologySaving"
            >
            <div v-if="errors['product_labels.singular_ar']" class="invalid-feedback">{{ errors['product_labels.singular_ar'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.pluralAr') }}</label>
            <input
              v-model="settings.product_labels.plural_ar"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['product_labels.plural_ar'] }"
              :disabled="!canEdit || terminologySaving"
            >
            <div v-if="errors['product_labels.plural_ar']" class="invalid-feedback">{{ errors['product_labels.plural_ar'][0] }}</div>
          </div>
        </div>

        <BaseButton v-if="canEdit" variant="primary" icon="bi-check-lg" :loading="terminologySaving" @click="saveTerminology">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Features -->
      <BaseCard :title="t('companySettings.featuresTitle')" icon="bi-toggles" class="mb-3">
        <p class="text-muted small">{{ t('companySettings.featuresDesc') }}</p>
        <div class="form-check form-switch mb-3">
          <input
            id="featuresQuotations"
            v-model="settings.features.quotations_enabled"
            class="form-check-input"
            type="checkbox"
            :disabled="!canEdit || featuresSaving"
          >
          <label class="form-check-label" for="featuresQuotations">{{ t('companySettings.quotationsEnabled') }}</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input
            id="featuresInvoices"
            v-model="settings.features.invoices_enabled"
            class="form-check-input"
            type="checkbox"
            :disabled="!canEdit || featuresSaving"
          >
          <label class="form-check-label" for="featuresInvoices">{{ t('companySettings.invoicesEnabled') }}</label>
        </div>

        <BaseButton v-if="canEdit" variant="primary" icon="bi-check-lg" :loading="featuresSaving" @click="saveFeatures">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- AI Service Provider -->
      <BaseCard :title="t('companySettings.aiTitle')" icon="bi-stars" class="mb-3">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.aiProvider') }}</label>
            <select
              v-model="settings.ai.provider"
              class="form-select"
              :class="{ 'is-invalid': errors['ai.provider'] }"
              :disabled="!canEdit || aiSaving"
            >
              <option :value="null">{{ t('companySettings.aiProviderDefault') }}</option>
              <option v-for="p in aiProviders" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <div v-if="errors['ai.provider']" class="invalid-feedback">{{ errors['ai.provider'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.aiModel') }}</label>
            <input
              v-model="settings.ai.model"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors['ai.model'] }"
              :placeholder="aiModelPlaceholder"
              :disabled="!canEdit || aiSaving"
            >
            <div v-if="errors['ai.model']" class="invalid-feedback">{{ errors['ai.model'][0] }}</div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.aiApiKey') }}</label>
            <div class="input-group ai-api-key-input-group">
              <input
                v-model="aiKeyInput"
                :type="showAiKey ? 'text' : 'password'"
                class="form-control"
                :class="{ 'is-invalid': errors['ai.api_key'] }"
                :placeholder="settings.ai.api_key_masked ?? t('companySettings.aiApiKeyPlaceholder')"
                :disabled="!canEdit || aiSaving"
                autocomplete="off"
              >
              <button
                class="btn btn-outline-secondary"
                type="button"
                :disabled="!canEdit || aiSaving || revealingAiKey"
                :aria-label="t(showAiKey ? 'companySettings.hideAiApiKey' : 'companySettings.showAiApiKey')"
                @click="toggleAiKeyVisibility"
              >
                <i class="bi" :class="showAiKey ? 'bi-eye-slash' : 'bi-eye'"></i>
              </button>
              <div v-if="errors['ai.api_key']" class="invalid-feedback">{{ errors['ai.api_key'][0] }}</div>
            </div>
            <small class="text-muted d-block mt-1">
              {{ settings.ai.api_key_set
                ? t('companySettings.aiApiKeySet', { masked: settings.ai.api_key_masked })
                : t('companySettings.aiApiKeyNotSet') }}
              {{ t('companySettings.aiApiKeyHint') }}
            </small>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('companySettings.aiBaseUrl') }}</label>
            <input
              v-model="settings.ai.base_url"
              type="url"
              class="form-control"
              :class="{ 'is-invalid': errors['ai.base_url'] }"
              placeholder="http://localhost:11434"
              :disabled="!canEdit || aiSaving"
            >
            <div v-if="errors['ai.base_url']" class="invalid-feedback">{{ errors['ai.base_url'][0] }}</div>
            <small class="text-muted d-block mt-1">{{ t('companySettings.aiBaseUrlHint') }}</small>
          </div>
        </div>

        <BaseButton v-if="canEdit" variant="primary" icon="bi-check-lg" :loading="aiSaving" @click="saveAi">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Logo -->
      <BaseCard :title="t('companySettings.logo')" icon="bi-image" class="mb-3">
        <div class="d-flex align-items-start gap-4 flex-wrap">
          <div>
            <img
              v-if="settings.logo_url"
              :src="settings.logo_url"
              :alt="settings.company_name ?? ''"
              class="border rounded"
              style="max-height: 96px; max-width: 240px; object-fit: contain;"
            >
            <div v-else class="border rounded bg-body-secondary d-flex align-items-center justify-content-center" style="width: 160px; height: 80px;">
              <i class="bi bi-image text-muted fs-2"></i>
            </div>
          </div>

          <div v-if="canEdit">
            <div class="d-flex gap-2 align-items-center flex-wrap">
              <label class="btn btn-outline-primary btn-sm" :class="{ disabled: logoSaving }">
                <i class="bi bi-upload me-1"></i>{{ t('companySettings.uploadLogo') }}
                <input type="file" accept="image/jpeg,image/png" class="d-none" :disabled="logoSaving" @change="handleLogoUpload">
              </label>
              <button
                v-if="settings.logo_url"
                class="btn btn-outline-danger btn-sm"
                :disabled="logoSaving"
                @click="removeLogo"
              >
                <i class="bi bi-trash me-1"></i>{{ t('companySettings.removeLogo') }}
              </button>
            </div>
            <small class="text-muted d-block mt-1">{{ t('companySettings.logoHint') }}</small>
          </div>
        </div>
      </BaseCard>
    </template>
  </div>
</template>
