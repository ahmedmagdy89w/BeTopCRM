<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: string | null | undefined
  type?: 'date' | 'datetime'
  placeholder?: string
  disabled?: boolean
  hasError?: boolean
  min?: string
  max?: string
}>(), {
  type: 'date',
  disabled: false,
  hasError: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  'blur': []
}>()

function onChange(event: Event) {
  const val = (event.target as HTMLInputElement).value
  emit('update:modelValue', val || null)
}
</script>

<template>
  <input
    :type="type === 'datetime' ? 'datetime-local' : 'date'"
    class="form-control"
    :class="{ 'is-invalid': hasError }"
    :value="modelValue ?? ''"
    :placeholder="placeholder"
    :disabled="disabled"
    :min="min"
    :max="max"
    @input="onChange"
    @blur="emit('blur')"
  >
</template>
