<script setup lang="ts">
import { onUnmounted, ref } from 'vue'
import { isAxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authService } from '@/services/authService'

const { t } = useI18n()
const router = useRouter()
const email = ref('')
const otp = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const requested = ref(false)
const loading = ref(false)
const message = ref('')
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const resendCooldown = ref(0)
let resendTimer: ReturnType<typeof setInterval> | undefined

function startResendCooldown() {
  resendCooldown.value = 60
  if (resendTimer) {
    clearInterval(resendTimer)
  }

  resendTimer = setInterval(() => {
    resendCooldown.value -= 1
    if (resendCooldown.value <= 0 && resendTimer) {
      clearInterval(resendTimer)
      resendTimer = undefined
    }
  }, 1000)
}

async function requestCode() {
  error.value = ''
  fieldErrors.value = {}
  loading.value = true

  try {
    const response = await authService.requestPasswordResetOtp(email.value)
    message.value = response.message
    requested.value = true
    startResendCooldown()
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
    } else {
      error.value = t('common.error')
    }
  } finally {
    loading.value = false
  }
}

async function resendCode() {
  if (resendCooldown.value > 0 || loading.value) {
    return
  }

  error.value = ''
  fieldErrors.value = {}
  otp.value = ''
  loading.value = true

  try {
    const response = await authService.requestPasswordResetOtp(email.value)
    message.value = response.message
    startResendCooldown()
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
    } else {
      error.value = t('common.error')
    }
  } finally {
    loading.value = false
  }
}

async function resetPassword() {
  error.value = ''
  fieldErrors.value = {}
  loading.value = true

  try {
    await authService.resetPasswordWithOtp({
      email: email.value,
      otp: otp.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    router.push({ name: 'login', query: { reset: 'success' } })
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
      error.value = err.response.data.message ?? t('common.error')
    } else {
      error.value = t('common.error')
    }
  } finally {
    loading.value = false
  }
}

onUnmounted(() => {
  if (resendTimer) {
    clearInterval(resendTimer)
  }
})
</script>

<template>
  <div class="auth-shell d-flex justify-content-center align-items-center vh-100">
    <div class="auth-card card border-0" style="width: 100%; max-width: 420px;">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <span class="auth-mark"><i class="bi bi-shield-lock"></i></span>
          <h3 class="mt-3 mb-1">{{ t('auth.forgotPasswordTitle') }}</h3>
          <p class="text-muted mb-0">{{ t('auth.forgotPasswordHelp') }}</p>
        </div>

        <div v-if="message" class="alert alert-success py-2">{{ message }}</div>
        <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

        <form v-if="!requested" @submit.prevent="requestCode">
          <div class="mb-3">
            <label for="reset-email" class="form-label">{{ t('auth.email') }}</label>
            <input id="reset-email" v-model="email" type="email" class="form-control" :class="{ 'is-invalid': fieldErrors.email }" required autofocus>
            <div v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email[0] }}</div>
          </div>
          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t('auth.sendCode') }}
          </button>
        </form>

        <form v-else @submit.prevent="resetPassword">
          <div class="mb-3">
            <label for="reset-otp" class="form-label">{{ t('auth.otp') }}</label>
            <input id="reset-otp" v-model="otp" type="text" inputmode="numeric" maxlength="6" class="form-control" :class="{ 'is-invalid': fieldErrors.otp }" required autofocus>
            <div v-if="fieldErrors.otp" class="invalid-feedback">{{ fieldErrors.otp[0] }}</div>
          </div>
          <div class="text-center mb-3">
            <button type="button" class="btn btn-link btn-sm p-0" :disabled="resendCooldown > 0 || loading" @click="resendCode">
              {{ resendCooldown > 0 ? t('auth.resendIn', { seconds: resendCooldown }) : t('auth.resendCode') }}
            </button>
          </div>
          <div class="mb-3">
            <label for="reset-password" class="form-label">{{ t('auth.newPassword') }}</label>
            <input id="reset-password" v-model="password" type="password" class="form-control" :class="{ 'is-invalid': fieldErrors.password }" required>
            <div v-if="fieldErrors.password" class="invalid-feedback">{{ fieldErrors.password[0] }}</div>
          </div>
          <div class="mb-3">
            <label for="reset-password-confirmation" class="form-label">{{ t('auth.confirmPassword') }}</label>
            <input id="reset-password-confirmation" v-model="passwordConfirmation" type="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t('auth.resetPassword') }}
          </button>
        </form>

        <p class="text-center mt-3 mb-0">
          <router-link :to="{ name: 'login' }">{{ t('auth.backToLogin') }}</router-link>
        </p>
      </div>
    </div>
  </div>
</template>
