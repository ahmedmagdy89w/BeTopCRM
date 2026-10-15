<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SelectOption } from '@/types/forms'

export type FilterControlType =
  | 'text'
  | 'number'
  | 'date'
  | 'datetime-local'
  | 'email'
  | 'tel'
  | 'url'
  | 'color'
  | 'select'
  | 'multi_select'

const props = withDefaults(defineProps<{
  label: string
  modelValue?: unknown
  type?: FilterControlType
  options?: SelectOption[]
  placeholder?: string
}>(), {
  modelValue: undefined,
  type: 'select',
  options: () => [],
  placeholder: undefined,
})

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
}>()

const { t } = useI18n()
const inputId = useId()
const currentValue = ref<unknown>(props.modelValue)

watch(() => props.modelValue, value => {
  currentValue.value = value
})

function update(value: unknown): void {
  currentValue.value = value
  emit('update:modelValue', value)
}

function onSelectChange(event: Event): void {
  const select = event.target as HTMLSelectElement

  if (props.type === 'multi_select') {
    const selected = Array.from(select.selectedOptions).map((selectedOption) => {
      const option = props.options.find(candidate => String(candidate.value) === selectedOption.value)

      return option?.value ?? selectedOption.value
    })

    update(selected.length ? selected : undefined)

    return
  }

  const value = select.value

  if (value === '') {
    update(undefined)

    return
  }

  const selectedOption = props.options.find(option => String(option.value) === value)

  update(selectedOption?.value ?? value)
}

function onInput(event: Event): void {
  const input = event.target as HTMLInputElement
  const value = input.value

  if (value === '') {
    update(undefined)

    return
  }

  update(props.type === 'number' ? Number(value) : value)
}

function isSelected(option: SelectOption): boolean {
  if (props.type === 'multi_select') {
    return Array.isArray(currentValue.value)
      && currentValue.value.some(value => String(value) === String(option.value))
  }

  return String(currentValue.value ?? '') === String(option.value)
}
</script>

<template>
  <div class="filter-control">
    <label class="form-label filter-control__label" :for="inputId">{{ label }}</label>

    <select
      v-if="type === 'select' || type === 'multi_select'"
      :id="inputId"
      class="form-select form-select-sm"
      :multiple="type === 'multi_select'"
      :aria-label="label"
      @change="onSelectChange"
    >
      <option v-if="type === 'select'" value="" :selected="currentValue == null || currentValue === ''">
        {{ placeholder ?? t('common.all') }}
      </option>
      <option
        v-for="option in options"
        :key="String(option.value)"
        :value="option.value"
        :selected="isSelected(option)"
      >
        {{ option.label }}
      </option>
    </select>

    <input
      v-else
      :id="inputId"
      :type="type"
      class="form-control form-control-sm"
      :value="currentValue ?? ''"
      :placeholder="placeholder"
      :aria-label="label"
      @input="onInput"
    >
  </div>
</template>

<style scoped>
.filter-control {
  width: 12rem;
  max-width: 100%;
}

.filter-control__label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

select[multiple] {
  min-height: 5.25rem;
}
</style>
