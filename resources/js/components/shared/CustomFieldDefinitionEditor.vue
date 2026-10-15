<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomFieldDefinitionInput, CustomFieldType } from '@/types'

interface DraftField extends CustomFieldDefinitionInput {
  localId: string
  optionsText: string
}

const props = withDefaults(defineProps<{
  modelValue: CustomFieldDefinitionInput[]
  errors?: Record<string, string[]>
  disabled?: boolean
}>(), {
  modelValue: () => [],
  errors: () => ({}),
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: CustomFieldDefinitionInput[]]
}>()

const { t } = useI18n()
const types: CustomFieldType[] = [
  'text', 'textarea', 'number', 'decimal', 'date', 'datetime', 'select',
  'multi_select', 'boolean', 'color', 'url', 'email', 'phone',
]
const fields = ref<DraftField[]>([])
let nextLocalId = 0
let publishing = false

watch(() => props.modelValue, (definitions) => {
  if (publishing) return

  fields.value = definitions.map(definition => ({
    ...definition,
    localId: definition.id !== undefined ? `field-${definition.id}` : `new-${nextLocalId++}`,
    optionsText: definition.options?.join(', ') ?? '',
  }))
}, { immediate: true, deep: true })

function publish(next: DraftField[]): void {
  fields.value = next
  publishing = true
  emit('update:modelValue', next.map(({ localId: _localId, optionsText, ...field }) => ({
    ...field,
    options: ['select', 'multi_select'].includes(field.type)
      ? optionsText.split(',').map(option => option.trim()).filter(Boolean)
      : null,
  })))
  queueMicrotask(() => {
    publishing = false
  })
}

function updateField(localId: string, patch: Partial<DraftField>): void {
  publish(fields.value.map(field => field.localId === localId ? { ...field, ...patch } : field))
}

function addField(): void {
  const localId = `new-${nextLocalId++}`

  publish([...fields.value, {
    localId,
    name: '',
    label: '',
    type: 'text',
    options: null,
    optionsText: '',
    is_required: false,
    is_filterable: false,
    sort_order: fields.value.length,
  }])
}

function removeField(localId: string): void {
  publish(fields.value.filter(field => field.localId !== localId))
}

function typeLabel(type: CustomFieldType): string {
  return t(`customFields.types.${type}`)
}
</script>

<template>
  <div>
    <div v-if="!fields.length" class="text-muted small mb-3">{{ t('customFields.noFields') }}</div>

    <div v-for="(field, index) in fields" :key="field.localId" class="border rounded p-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ t('customFields.fieldName') }}</label>
          <input
            class="form-control"
            :class="{ 'is-invalid': errors[`product_fields.${index}.name`] || errors[`definitions.${index}.name`] }"
            :value="field.name"
            :disabled="disabled"
            @input="updateField(field.localId, { name: ($event.target as HTMLInputElement).value })"
          >
          <div v-if="errors[`product_fields.${index}.name`] || errors.name" class="invalid-feedback">
            {{ errors[`product_fields.${index}.name`]?.[0] ?? errors.name?.[0] }}
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label">{{ t('customFields.fieldLabel') }}</label>
          <input
            class="form-control"
            :class="{ 'is-invalid': errors.label }"
            :value="field.label"
            :disabled="disabled"
            @input="updateField(field.localId, { label: ($event.target as HTMLInputElement).value })"
          >
          <div v-if="errors.label" class="invalid-feedback">{{ errors.label[0] }}</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">{{ t('customFields.fieldType') }}</label>
          <select
            class="form-select"
            :value="field.type"
            :disabled="disabled"
            @change="updateField(field.localId, { type: ($event.target as HTMLSelectElement).value as CustomFieldType })"
          >
            <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
          </select>
        </div>

        <div v-if="field.type === 'select' || field.type === 'multi_select'" class="col-md-3">
          <label class="form-label">{{ t('customFields.options') }}</label>
          <input
            class="form-control"
            :class="{ 'is-invalid': errors.options }"
            :value="field.optionsText"
            :placeholder="t('customFields.optionsPlaceholder')"
            :disabled="disabled"
            @input="updateField(field.localId, { optionsText: ($event.target as HTMLInputElement).value })"
          >
        </div>

        <div class="col-md-auto">
          <div class="form-check">
            <input
              :id="`${field.localId}-required`"
              class="form-check-input"
              type="checkbox"
              :checked="field.is_required"
              :disabled="disabled"
              @change="updateField(field.localId, { is_required: ($event.target as HTMLInputElement).checked })"
            >
            <label class="form-check-label" :for="`${field.localId}-required`">{{ t('customFields.isRequired') }}</label>
          </div>
        </div>

        <div class="col-md-auto">
          <div class="form-check">
            <input
              :id="`${field.localId}-filterable`"
              class="form-check-input"
              type="checkbox"
              :checked="field.is_filterable"
              :disabled="disabled"
              @change="updateField(field.localId, { is_filterable: ($event.target as HTMLInputElement).checked })"
            >
            <label class="form-check-label" :for="`${field.localId}-filterable`">{{ t('customFields.isFilterable') }}</label>
          </div>
        </div>

        <div class="col-md-auto ms-md-auto">
          <button type="button" class="btn btn-outline-danger btn-sm" :disabled="disabled" @click="removeField(field.localId)">
            <i class="bi bi-trash"></i>
            <span class="visually-hidden">{{ t('common.delete') }}</span>
          </button>
        </div>
      </div>
    </div>

    <button type="button" class="btn btn-outline-primary btn-sm" :disabled="disabled" @click="addField">
      <i class="bi bi-plus-lg me-1"></i>{{ t('customFields.addField') }}
    </button>
  </div>
</template>
