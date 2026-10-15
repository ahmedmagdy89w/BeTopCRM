<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const error = ref<Error | null>(null)
const hasError = ref(false)

onErrorCaptured((err: Error) => {
  error.value = err
  hasError.value = true
  console.error('[ErrorBoundary]', err)
  return false // prevent propagation
})

function retry() {
  hasError.value = false
  error.value = null
}
</script>

<template>
  <div v-if="hasError" class="text-center py-5">
    <i class="bi bi-exclamation-triangle fs-1 text-warning d-block mb-3"></i>
    <h5 class="text-muted mb-2">{{ t('common.somethingWentWrong') }}</h5>
    <p class="text-muted small mb-3">{{ error?.message }}</p>
    <button class="btn btn-primary btn-sm" @click="retry">
      <i class="bi bi-arrow-clockwise me-1"></i>{{ t('common.retry') }}
    </button>
  </div>
  <slot v-else />
</template>
