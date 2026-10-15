<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FormFieldSchema, FormErrors } from '@/types/forms'
import BaseModal from '@/components/base/BaseModal.vue'
import DynamicForm from '@/components/form/DynamicForm.vue'

const { t } = useI18n()

const props = defineProps<{
  modelValue: boolean
  title: string
  schema: FormFieldSchema[]
  submitFn: (data: Record<string, unknown>) => Promise<unknown>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  created: [entity: unknown]
}>()

const formData = ref<Record<string, unknown>>({})
const errors = ref<FormErrors>({})
const loading = ref(false)

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    const result = await props.submitFn(formData.value)
    emit('created', result)
    emit('update:modelValue', false)
    formData.value = {}
  } catch (err: unknown) {
    const axiosErr = err as { response?: { status?: number; data?: { errors?: FormErrors } } }
    if (axiosErr.response?.status === 422) {
      errors.value = axiosErr.response.data?.errors ?? {}
    }
  } finally {
    loading.value = false
  }
}

function handleClose() {
  formData.value = {}
  errors.value = {}
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="title"
    size="md"
    @update:model-value="emit('update:modelValue', $event)"
    @close="handleClose"
  >
    <DynamicForm
      :schema="schema"
      :model-value="formData"
      :errors="errors"
      :loading="loading"
      @update:model-value="formData = $event"
      @submit="handleSubmit"
    />

    <template #footer>
      <button
        type="button"
        class="btn btn-secondary"
        @click="emit('update:modelValue', false)"
      >
        {{ t('common.cancel') }}
      </button>
      <button
        type="button"
        class="btn btn-primary"
        :disabled="loading"
        @click="handleSubmit"
      >
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
        {{ t('common.save') }}
      </button>
    </template>
  </BaseModal>
</template>
