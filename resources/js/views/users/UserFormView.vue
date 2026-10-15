<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { userService } from '@/services/userService'
import { roleService } from '@/services/roleService'
import type { Role } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)

// Full IANA list from the browser; falls back to common zones where unsupported.
const COMMON_TIMEZONES = [
  'UTC',
  'Africa/Cairo',
  'Africa/Johannesburg',
  'America/Chicago',
  'America/Los_Angeles',
  'America/New_York',
  'America/Toronto',
  'Asia/Dubai',
  'Asia/Istanbul',
  'Asia/Karachi',
  'Asia/Kolkata',
  'Asia/Riyadh',
  'Asia/Singapore',
  'Australia/Sydney',
  'Europe/Berlin',
  'Europe/London',
  'Europe/Paris',
  'Pacific/Auckland',
]

const timezones = computed(() => {
  const intl = Intl as unknown as { supportedValuesOf?: (key: string) => string[] }
  const all = typeof intl.supportedValuesOf === 'function'
    ? intl.supportedValuesOf('timeZone')
    : [...COMMON_TIMEZONES]
  // Keep a stored custom value selectable even if absent from the list.
  return form.value.timezone && !all.includes(form.value.timezone)
    ? [form.value.timezone, ...all]
    : all
})

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'sales_rep',
  phone: '',
  timezone: 'UTC',
  locale: 'en',
  is_active: true,
})
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)
const availableRoles = ref<Role[]>([])

async function fetchRoles() {
  availableRoles.value = await roleService.list()
}

async function fetch() {
  if (!id) return
  loading.value = true
  try {
    const user = await userService.get(id)
    form.value = {
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
      phone: user.phone ?? '',
      timezone: user.timezone,
      locale: user.locale,
      is_active: user.is_active,
    }
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  errors.value = {}
  saving.value = true
  try {
    const data: Record<string, unknown> = { ...form.value }
    if (isEdit.value && !data.password) delete data.password
    if (isEdit.value && id) {
      await userService.update(id, data)
    } else {
      await userService.create(data)
    }
    router.push({ name: 'users.index' })
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetch(), fetchRoles()])
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('users.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <form v-else @submit.prevent="handleSubmit">
      <BaseCard>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('common.name') }}</label>
            <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('common.email') }}</label>
            <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }">
            <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('auth.password') }} <small v-if="isEdit" class="text-muted">({{ t('users.leaveBlank') }})</small></label>
            <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': errors.password }" autocomplete="new-password">
            <div v-if="errors.password" class="invalid-feedback">{{ errors.password[0] }}</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ t('users.role') }}</label>
            <select v-model="form.role" class="form-select" :class="{ 'is-invalid': errors.role }">
              <option v-for="r in availableRoles" :key="r.id" :value="r.name">
                {{ r.name.replace(/_/g, ' ') }}
                <template v-if="r.is_system"> ✦</template>
              </option>
            </select>
            <div v-if="errors.role" class="invalid-feedback">{{ errors.role[0] }}</div>
            <div class="form-text">{{ t('roles.manageRoles') }}</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('common.phone') }}</label>
            <input v-model="form.phone" type="tel" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('users.timezone') }}</label>
            <select v-model="form.timezone" class="form-select" :class="{ 'is-invalid': errors.timezone }">
              <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
            </select>
            <div v-if="errors.timezone" class="invalid-feedback">{{ errors.timezone[0] }}</div>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">{{ t('users.locale') }}</label>
            <select v-model="form.locale" class="form-select">
              <option value="en">English</option>
              <option value="ar">العربية</option>
            </select>
          </div>
        </div>

        <div v-if="isEdit" class="form-check mb-3">
          <input v-model="form.is_active" class="form-check-input" type="checkbox" id="userActive">
          <label class="form-check-label" for="userActive">{{ t('common.active') }}</label>
        </div>

        <div class="d-flex gap-2">
          <BaseButton type="submit" variant="primary" icon="bi-check-lg" :loading="saving">
            {{ t('common.save') }}
          </BaseButton>
          <BaseButton variant="outline-secondary" @click="router.back()">
            {{ t('common.cancel') }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>
  </div>
</template>
