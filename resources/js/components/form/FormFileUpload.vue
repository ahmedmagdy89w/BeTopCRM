<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  loading?: boolean
  accept?: string
  maxFiles?: number
}>()

const emit = defineEmits<{
  upload: [files: File[]]
}>()

const fileInput = ref<HTMLInputElement>()

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.length) return
  emit('upload', Array.from(input.files))
  input.value = ''
}

function openPicker() {
  fileInput.value?.click()
}
</script>

<template>
  <div>
    <input
      ref="fileInput"
      type="file"
      class="d-none"
      :accept="accept"
      :multiple="(maxFiles ?? 5) > 1"
      @change="onFileChange"
    >
    <button
      type="button"
      class="btn btn-outline-primary btn-sm"
      :disabled="loading"
      @click="openPicker"
    >
      <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
      <i v-else class="bi bi-upload me-1"></i>
      {{ t('common.upload') }}
    </button>
  </div>
</template>
