<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import type { SelectOption } from '@/types/forms'
import api from '@/services/api'

const props = withDefaults(defineProps<{
  modelValue: string | number | Array<string | number> | null | undefined
  options?: SelectOption[]
  optionsUrl?: string
  optionsLabelKey?: string
  optionsValueKey?: string
  placeholder?: string
  disabled?: boolean
  hasError?: boolean
  quickAdd?: boolean
  multiple?: boolean
}>(), {
  options: () => [],
  optionsLabelKey: 'name',
  optionsValueKey: 'id',
  disabled: false,
  hasError: false,
  quickAdd: false,
  multiple: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | Array<string | number> | null]
  'quick-add': []
  'blur': []
}>()

const remoteOptions = ref<SelectOption[]>([])
const loadingOptions = ref(false)

const allOptions = computed<SelectOption[]>(() => {
  if (props.optionsUrl) return remoteOptions.value
  return props.options
})

async function fetchOptions() {
  if (!props.optionsUrl) return
  loadingOptions.value = true
  try {
    const response = await api.get(props.optionsUrl)
    const data: unknown[] = response.data.data ?? response.data
    remoteOptions.value = data.map((item: unknown) => {
      const record = item as Record<string, unknown>
      return {
        label: String(record[props.optionsLabelKey] ?? ''),
        value: record[props.optionsValueKey] as string | number,
      }
    })
  } finally {
    loadingOptions.value = false
  }
}

function onChange(event: Event) {
  const select = event.target as HTMLSelectElement

  if (props.multiple) {
    const selectedValues = Array.from(select.selectedOptions).map((selected) => {
      const option = allOptions.value.find(item => String(item.value) === selected.value)

      return option?.value ?? selected.value
    })

    emit('update:modelValue', selectedValues)

    return
  }

  const val = select.value
  emit('update:modelValue', val === '' ? null : (isNaN(Number(val)) ? val : Number(val)))
}

function isSelected(option: SelectOption): boolean {
  if (props.multiple) {
    return Array.isArray(props.modelValue)
      && props.modelValue.some(value => String(value) === String(option.value))
  }

  return !Array.isArray(props.modelValue) && option.value == props.modelValue
}

/** Prepend a new option and select it (used after quick-add) */
function addOption(option: SelectOption) {
  remoteOptions.value = [option, ...remoteOptions.value]
  emit('update:modelValue', option.value)
}

watch(() => props.optionsUrl, () => fetchOptions(), { immediate: false })

onMounted(() => {
  if (props.optionsUrl) fetchOptions()
})

defineExpose({ addOption, fetchOptions })
</script>

<template>
  <div class="d-flex gap-1">
    <select
      class="form-select"
      :class="{ 'is-invalid': hasError }"
      :multiple="multiple"
      :disabled="disabled || loadingOptions"
      @change="onChange"
      @blur="emit('blur')"
    >
      <option v-if="!multiple" value="" :selected="modelValue == null || modelValue === ''">{{ placeholder || '—' }}</option>
      <option
        v-for="opt in allOptions"
        :key="opt.value"
        :value="opt.value"
        :selected="isSelected(opt)"
      >
        {{ opt.label }}
      </option>
    </select>
    <button
      v-if="quickAdd"
      type="button"
      class="btn btn-outline-primary btn-sm flex-shrink-0"
      :disabled="disabled"
      @click="emit('quick-add')"
      :title="$t('common.quickAdd')"
    >
      <i class="bi bi-plus-lg"></i>
    </button>
  </div>
</template>
