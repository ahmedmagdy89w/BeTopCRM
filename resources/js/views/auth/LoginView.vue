<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { isAxiosError } from 'axios'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

async function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  loading.value = true

  try {
    await auth.login({ email: email.value, password: password.value })
    const redirect = (route.query.redirect as string) || '/dashboard'
    router.push(redirect)
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
    } else if (isAxiosError(err) && err.response?.status === 401) {
      error.value = t('errors.invalidCredentials')
    } else {
      error.value = t('common.error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-shell d-flex justify-content-center align-items-center vh-100">
    <div class="auth-card card border-0" style="width: 100%; max-width: 420px;">
      <div class="card-body p-4">
        <div class="text-center mb-4">
            <span class="auth-mark"><i class="bi bi-command"></i></span>
            <h3 class="mt-3 mb-1">{{ t('auth.login') }}</h3>
            <p class="text-muted mb-0">Sign in to your workspace</p>
        </div>

        <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <form @submit.prevent="handleSubmit">
          <!-- Email -->
          <div class="mb-3">
            <label for="login-email" class="form-label">{{ t('auth.email') }}</label>
            <input
              id="login-email"
              v-model="email"
              type="email"
              class="form-control"
              :class="{ 'is-invalid': fieldErrors.email }"
              required
              autofocus
            >
            <div v-if="fieldErrors.email" class="invalid-feedback">
              {{ fieldErrors.email[0] }}
            </div>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label for="login-password" class="form-label">{{ t('auth.password') }}</label>
            <input
              id="login-password"
              v-model="password"
              type="password"
              class="form-control"
              :class="{ 'is-invalid': fieldErrors.password }"
              required
            >
            <div v-if="fieldErrors.password" class="invalid-feedback">
              {{ fieldErrors.password[0] }}
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t('auth.login') }}
          </button>
        </form>

        <p class="text-center mt-3 mb-0">
          <router-link :to="{ name: 'forgot-password' }">{{ t('auth.forgotPassword') }}</router-link>
        </p>

      </div>
    </div>
  </div>
</template>
