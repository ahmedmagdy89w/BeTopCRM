<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FormFieldSchema, FormErrors, SelectOption } from '@/types/forms'
import type { CustomFieldDefinition } from '@/types'
import FormField from '@/components/form/FormField.vue'
import QuickAddModal from '@/components/form/QuickAddModal.vue'
import api from '@/services/api'
import { customFieldDefinitionsToSchema } from '@/utils/customFieldTypes'

const { t } = useI18n()

const props = withDefaults(defineProps<{
  schema: FormFieldSchema[]
  customFields?: CustomFieldDefinition[]
  modelValue: Record<string, unknown>
  errors?: FormErrors
  loading?: boolean
  columns?: 1 | 2 | 3
}>(), {
  schema: () => [],
  customFields: () => [],
  errors: () => ({}),
  loading: false,
  columns: 1,
})

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>]
  submit: []
}>()

// Template refs for each FormField (to call addOption on FormSelect)
const fieldRefs = ref<Record<string, InstanceType<typeof FormField> | null>>({})
function setFieldRef(key: string, el: unknown) {
  fieldRefs.value[key] = el as InstanceType<typeof FormField> | null
}

// Quick-add modal state
const quickAddOpen = ref(false)
const activeField = ref<FormFieldSchema | null>(null)

function onQuickAdd(field: FormFieldSchema) {
  activeField.value = field
  quickAddOpen.value = true
}

async function quickAddSubmit(data: Record<string, unknown>): Promise<unknown> {
  const endpoint = activeField.value!.quickAdd!.service
  const res = await api.post(endpoint, data)
  return res.data.data ?? res.data
}

function onQuickAddCreated(entity: unknown) {
  const field = activeField.value
  if (!field) return

  const record = entity as Record<string, unknown>
  const labelKey = field.optionsLabelKey ?? 'name'
  const valueKey = field.optionsValueKey ?? 'id'
  const option: SelectOption = {
    label: String(record[labelKey] ?? ''),
    value: record[valueKey] as string | number,
  }

  // Inject the new option into the FormSelect and select it
  fieldRefs.value[field.key]?.selectRef?.addOption(option)
  emit('update:modelValue', setPathValue(props.modelValue, field.key, option.value))
  quickAddOpen.value = false
}

const allFields = computed(() => [
  ...props.schema,
  ...customFieldDefinitionsToSchema(props.customFields),
])

const visibleFields = computed(() =>
  allFields.value.filter(f => {
    if (typeof f.visible === 'function') return f.visible(props.modelValue)
    return f.visible !== false
  }),
)

function updateField(key: string, value: unknown) {
  emit('update:modelValue', setPathValue(props.modelValue, key, value))
  editedFieldsSinceServerErrors.value.add(key)
  // Re-validate touched fields live as the user types
  if (touchedFields.value.has(key)) {
    const field = allFields.value.find(f => f.key === key)
    if (field) clientErrors.value = { ...clientErrors.value, [key]: validateField(field, value) }
  }
}

function getFieldValue(path: string): unknown {
  return path.split('.').reduce<unknown>((value, segment) => {
    if (value === null || typeof value !== 'object') return undefined

    return (value as Record<string, unknown>)[segment]
  }, props.modelValue)
}

function setPathValue(source: Record<string, unknown>, path: string, value: unknown): Record<string, unknown> {
  const segments = path.split('.')
  const result: Record<string, unknown> = { ...source }
  let current = result
  let sourceCurrent: Record<string, unknown> = source

  for (const segment of segments.slice(0, -1)) {
    const existing = sourceCurrent[segment]
    const next = existing !== null && typeof existing === 'object' && !Array.isArray(existing)
      ? { ...(existing as Record<string, unknown>) }
      : {}

    current[segment] = next
    current = next
    sourceCurrent = existing !== null && typeof existing === 'object' && !Array.isArray(existing)
      ? existing as Record<string, unknown>
      : {}
  }

  current[segments[segments.length - 1]!] = value

  return result
}

const gridClass = computed(() => {
  if (props.columns === 1) return ''
  return `row row-cols-1 row-cols-md-${props.columns} g-3`
})

// ─── Validation engine ───────────────────────────────────────────────
const touchedFields = ref(new Set<string>())
const clientErrors = ref<FormErrors>({})
const editedFieldsSinceServerErrors = ref(new Set<string>())

watch(() => props.errors, () => {
  editedFieldsSinceServerErrors.value = new Set()
}, { deep: true })

function isEmpty(value: unknown): boolean {
  if (value === null || value === undefined || value === '') return true
  if (Array.isArray(value) && value.length === 0) return true
  return false
}

function validateField(field: FormFieldSchema, value: unknown): string[] {
  if (field.required && isEmpty(value)) return [t('common.required')]
  if (field.rules && !isEmpty(value)) {
    const result = field.rules.safeParse(value)
    if (!result.success) {
      return result.error.errors.map(e => e.message)
    }
  }
  return []
}

function validateAll(): boolean {
  const newErrors: FormErrors = {}
  for (const field of visibleFields.value) {
    touchedFields.value.add(field.key)
    const errs = validateField(field, getFieldValue(field.key))
    if (errs.length) newErrors[field.key] = errs
  }
  clientErrors.value = newErrors
  return Object.keys(newErrors).length === 0
}

function onFieldBlur(key: string) {
  touchedFields.value.add(key)
  const field = allFields.value.find(f => f.key === key)
  if (!field) return
  const errs = validateField(field, getFieldValue(key))
  clientErrors.value = { ...clientErrors.value, [key]: errs }
}

const mergedErrors = computed<FormErrors>(() => {
  const merged = { ...props.errors }
  for (const key of editedFieldsSinceServerErrors.value) {
    // Only edits made after the response clear server errors.
    merged[key] = clientErrors.value[key] ?? []
  }
  return merged
})

function colClass(field: FormFieldSchema): string {
  if (props.columns === 1) return ''
  if (field.colSpan && field.colSpan >= props.columns) return `col-12`
  if (field.colSpan === 2 && props.columns === 3) return 'col-md-8'
  return 'col'
}
</script>

<template>
  <form @submit.prevent="validateAll() && emit('submit')" :class="gridClass">
    <div
      v-for="field in visibleFields"
      :key="field.key"
      :class="colClass(field)"
    >
      <FormField
        :ref="(el) => setFieldRef(field.key, el)"
        :field="field"
        :model-value="getFieldValue(field.key)"
        :errors="mergedErrors"
        :columns="props.columns"
        @update:model-value="updateField(field.key, $event)"
        @blur="onFieldBlur(field.key)"
        @quick-add="onQuickAdd($event)"
      />
    </div>
    <!-- Hidden submit button so Enter key submits -->
    <button type="submit" class="d-none" :disabled="loading"></button>
  </form>

  <QuickAddModal
    v-if="activeField?.quickAdd"
    v-model="quickAddOpen"
    :title="activeField.quickAdd.title"
    :schema="activeField.quickAdd.fields"
    :submit-fn="quickAddSubmit"
    @created="onQuickAddCreated"
  />
</template>
