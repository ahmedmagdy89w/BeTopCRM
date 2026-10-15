<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { settingsService } from '@/services/settingsService'
import type { UserSettings } from '@/services/settingsService'
import { useUIStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseAlert from '@/components/base/BaseAlert.vue'

const { t } = useI18n()
const ui = useUIStore()

const settings = ref<UserSettings | null>(null)
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const successMessage = ref('')

// Password form
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordErrors = ref<Record<string, string[]>>({})
const passwordSaving = ref(false)
const passwordSuccess = ref('')

// Avatar
const avatarSaving = ref(false)

const NOTIFICATION_TYPES = [
  'lead_assigned', 'lead_status_changed', 'follow_up_reminder',
  'opportunity_expiring', 'customer_health_alert', 'task_due',
]

async function fetch() {
  loading.value = true
  try {
    settings.value = await settingsService.get()
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  if (!settings.value) return
  errors.value = {}
  successMessage.value = ''
  saving.value = true
  try {
    const { id, name, email, avatar_url, role, created_at, ...data } = settings.value
    settings.value = await settingsService.update(data as Partial<UserSettings>)
    ui.setLocale(settings.value.locale as 'en' | 'ar')
    successMessage.value = t('common.saveSuccess')
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  passwordErrors.value = {}
  passwordSuccess.value = ''
  passwordSaving.value = true
  try {
    await settingsService.changePassword(passwordForm.value)
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    passwordSuccess.value = t('settings.passwordChanged')
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      passwordErrors.value = e.response.data.errors
    }
  } finally {
    passwordSaving.value = false
  }
}

async function uploadAvatar(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file || !settings.value) return
  avatarSaving.value = true
  try {
    const result = await settingsService.uploadAvatar(file)
    settings.value.avatar_url = result.avatar_url
  } finally {
    avatarSaving.value = false
    input.value = ''
  }
}

async function removeAvatar() {
  if (!settings.value) return
  await settingsService.deleteAvatar()
  settings.value.avatar_url = undefined
}

onMounted(fetch)
</script>

<template>
  <div>
    <h2 class="mb-3">{{ t('settings.title') }}</h2>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <template v-else-if="settings">
      <!-- Profile Section -->
      <BaseCard :title="t('settings.profile')" icon="bi-person" class="mb-3">
        <BaseAlert v-if="successMessage" variant="success" dismissible @dismissed="successMessage = ''">
          {{ successMessage }}
        </BaseAlert>

        <div class="row">
          <!-- Avatar -->
          <div class="col-md-3 text-center mb-3">
            <div class="mb-2">
              <img v-if="settings.avatar_url" :src="settings.avatar_url" alt="Avatar" class="rounded-circle" style="width: 96px; height: 96px; object-fit: cover;">
              <div v-else class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 96px; height: 96px;">
                <i class="bi bi-person-fill text-white fs-1"></i>
              </div>
            </div>
            <div class="d-flex gap-1 justify-content-center">
              <label class="btn btn-outline-primary btn-sm">
                <i class="bi bi-upload me-1"></i>{{ t('settings.uploadAvatar') }}
                <input type="file" accept="image/jpeg,image/png" class="d-none" @change="uploadAvatar">
              </label>
              <button v-if="settings.avatar_url" class="btn btn-outline-danger btn-sm" @click="removeAvatar">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>

          <!-- Profile Fields -->
          <div class="col-md-9">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('common.name') }}</label>
                <input :value="settings.name" type="text" class="form-control" disabled>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('common.email') }}</label>
                <input :value="settings.email" type="email" class="form-control" disabled>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('common.phone') }}</label>
                <input v-model="settings.phone" type="tel" class="form-control" :class="{ 'is-invalid': errors.phone }">
                <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone[0] }}</div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('users.timezone') }}</label>
                <input v-model="settings.timezone" type="text" class="form-control" :class="{ 'is-invalid': errors.timezone }">
                <div v-if="errors.timezone" class="invalid-feedback">{{ errors.timezone[0] }}</div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('users.locale') }}</label>
                <select v-model="settings.locale" class="form-select">
                  <option value="en">English</option>
                  <option value="ar">العربية</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ t('settings.dateFormat') }}</label>
                <select v-model="settings.date_format" class="form-select">
                  <option value="Y-m-d">2026-03-13</option>
                  <option value="d/m/Y">13/03/2026</option>
                  <option value="m/d/Y">03/13/2026</option>
                  <option value="d-m-Y">13-03-2026</option>
                  <option value="d M Y">13 Mar 2026</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <BaseButton variant="primary" icon="bi-check-lg" :loading="saving" @click="saveSettings">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Notification Preferences -->
      <BaseCard :title="t('settings.notificationPrefs')" icon="bi-bell" class="mb-3">
        <div class="row">
          <div v-for="notifType in NOTIFICATION_TYPES" :key="notifType" class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input
                class="form-check-input"
                type="checkbox"
                :id="`notif-${notifType}`"
                :checked="settings.notification_preferences[notifType] !== false"
                @change="settings!.notification_preferences[notifType] = ($event.target as HTMLInputElement).checked"
              >
              <label class="form-check-label" :for="`notif-${notifType}`">
                {{ t(`settings.notifTypes.${notifType}`) }}
              </label>
            </div>
          </div>
        </div>
        <BaseButton variant="primary" icon="bi-check-lg" :loading="saving" class="mt-2" @click="saveSettings">
          {{ t('common.save') }}
        </BaseButton>
      </BaseCard>

      <!-- Change Password -->
      <BaseCard :title="t('settings.changePassword')" icon="bi-shield-lock" class="mb-3">
        <BaseAlert v-if="passwordSuccess" variant="success" dismissible @dismissed="passwordSuccess = ''">
          {{ passwordSuccess }}
        </BaseAlert>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('settings.currentPassword') }}</label>
            <input v-model="passwordForm.current_password" type="password" class="form-control" :class="{ 'is-invalid': passwordErrors.current_password }" autocomplete="current-password">
            <div v-if="passwordErrors.current_password" class="invalid-feedback">{{ passwordErrors.current_password[0] }}</div>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('settings.newPassword') }}</label>
            <input v-model="passwordForm.password" type="password" class="form-control" :class="{ 'is-invalid': passwordErrors.password }" autocomplete="new-password">
            <div v-if="passwordErrors.password" class="invalid-feedback">{{ passwordErrors.password[0] }}</div>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('auth.confirmPassword') }}</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="form-control" autocomplete="new-password">
          </div>
        </div>
        <BaseButton variant="warning" icon="bi-key" :loading="passwordSaving" @click="changePassword">
          {{ t('settings.changePassword') }}
        </BaseButton>
      </BaseCard>
    </template>
  </div>
</template>
