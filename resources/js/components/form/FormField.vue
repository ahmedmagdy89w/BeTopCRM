<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import type { FormFieldSchema, FormErrors } from '@/types/forms'
import FormSelect from '@/components/form/FormSelect.vue'
import FormDatePicker from '@/components/form/FormDatePicker.vue'
import FormTagInput from '@/components/form/FormTagInput.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const props = withDefaults(defineProps<{
  field: FormFieldSchema
  modelValue: unknown
  errors?: FormErrors
  columns?: 1 | 2 | 3
}>(), {
  columns: 1,
})

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
  'quick-add': [field: FormFieldSchema]
  'blur': []
}>()

const selectRef = useTemplateRef<InstanceType<typeof FormSelect>>('selectRef')

const fieldErrors = computed(() => props.errors?.[props.field.key] ?? [])
const hasError = computed(() => fieldErrors.value.length > 0)

const showLabelSpacer = computed(() => {
  if (props.field.type !== 'boolean') return false
  const cols = props.columns ?? 1
  if (cols <= 1) return false
  if (props.field.colSpan && props.field.colSpan >= cols) return false
  return true
})

function update(value: unknown) {
  emit('update:modelValue', value)
}

function onBlur() {
  emit('blur')
}

function onInput(event: Event) {
  const target = event.target as HTMLInputElement
  if (props.field.type === 'number') {
    update(target.value === '' ? null : Number(target.value))
  } else if (props.field.type === 'boolean') {
    update(target.checked)
  } else {
    update(target.value)
  }
}

defineExpose({ selectRef })
</script>

<template>
  <div v-if="field.type !== 'hidden'" class="mb-3">
    <label v-if="field.type !== 'boolean'" :for="`field-${field.key}`" class="form-label">
      {{ field.label }}
      <span v-if="field.required" class="text-danger">*</span>
    </label>
    <div
      v-else-if="showLabelSpacer"
      class="form-label d-none d-md-block invisible"
      aria-hidden="true"
    >
      &nbsp;
    </div>

    <!-- Select / Multi-Select -->
    <FormSelect
      v-if="field.type === 'select' || field.type === 'multi_select'"
      ref="selectRef"
      :model-value="(modelValue as string | number | Array<string | number> | null | undefined)"
      :options="field.options"
      :options-url="field.optionsUrl"
      :options-label-key="field.optionsLabelKey"
      :options-value-key="field.optionsValueKey"
      :placeholder="field.placeholder"
      :disabled="field.disabled"
      :has-error="hasError"
      :quick-add="!!field.quickAdd"
      :multiple="field.type === 'multi_select' || !!field.multiple"
      @update:model-value="update($event)"
      @blur="onBlur"
      @quick-add="emit('quick-add', field)"
    />

    <!-- Date / DateTime -->
    <FormDatePicker
      v-else-if="field.type === 'date' || field.type === 'datetime'"
      :model-value="(modelValue as string | null | undefined)"
      :type="field.type"
      :placeholder="field.placeholder"
      :disabled="field.disabled"
      :has-error="hasError"
      :min="field.min"
      :max="field.max"
      @update:model-value="update($event)"
      @blur="onBlur"
    />

    <!-- Radio group -->
    <div v-else-if="field.type === 'radio'" class="d-flex flex-wrap gap-3">
      <div v-for="opt in (field.options ?? [])" :key="opt.value" class="form-check">
        <input
          :id="`field-${field.key}-${opt.value}`"
          class="form-check-input"
          :class="{ 'is-invalid': hasError }"
          type="radio"
          :name="`field-${field.key}`"
          :value="opt.value"
          :checked="modelValue == opt.value"
          :disabled="field.disabled"
          @change="update(opt.value)"
          @blur="onBlur"
        >
        <label class="form-check-label" :for="`field-${field.key}-${opt.value}`">
          {{ opt.label }}
        </label>
      </div>
    </div>

    <!-- Tags -->
    <FormTagInput
      v-else-if="field.type === 'tag'"
      :model-value="(modelValue as number[] | undefined) ?? []"
      @update:model-value="update($event); onBlur()"
    />

    <!-- User picker (backend search) -->
    <UserPicker
      v-else-if="field.type === 'user'"
      :model-value="(modelValue as number | null | undefined)"
      :placeholder="field.placeholder"
      :disabled="field.disabled"
      @update:model-value="update($event)"
      @blur="onBlur"
    />

    <!-- Textarea -->
    <textarea
      v-else-if="field.type === 'textarea'"
      :id="`field-${field.key}`"
      class="form-control"
      :class="{ 'is-invalid': hasError }"
      :value="(modelValue as string) ?? ''"
      :placeholder="field.placeholder"
      :disabled="field.disabled"
      :required="field.required"
      rows="3"
      @input="onInput"
      @blur="onBlur"
    ></textarea>

    <!-- Boolean / Checkbox -->
    <div
      v-else-if="field.type === 'boolean'"
      :class="{ 'form-check-aligned': showLabelSpacer }"
    >
      <div class="form-check" :class="{ 'mb-0': showLabelSpacer }">
        <input
          :id="`field-${field.key}`"
          class="form-check-input"
          :class="{ 'is-invalid': hasError }"
          type="checkbox"
          :checked="!!modelValue"
          :disabled="field.disabled"
          @change="onInput"
        >
        <label class="form-check-label" :for="`field-${field.key}`">
          {{ field.label }}
          <span v-if="field.required" class="text-danger">*</span>
        </label>
      </div>
    </div>

    <!-- File -->
    <input
      v-else-if="field.type === 'file'"
      :id="`field-${field.key}`"
      type="file"
      class="form-control"
      :class="{ 'is-invalid': hasError }"
      :disabled="field.disabled"
      @change="update(($event.target as HTMLInputElement).files?.[0] ?? null)"
    >

    <!-- Color picker -->
    <input
      v-else-if="field.type === 'color'"
      :id="`field-${field.key}`"
      type="color"
      class="form-control form-control-color"
      :class="{ 'is-invalid': hasError }"
      :value="(modelValue as string) || '#000000'"
      :disabled="field.disabled"
      @input="onInput"
      @blur="onBlur"
    >

    <!-- Standard inputs: text, email, tel, number, url, password -->
    <input
      v-else
      :id="`field-${field.key}`"
      :type="field.type"
      class="form-control"
      :class="{ 'is-invalid': hasError }"
      :value="(modelValue as string | number) ?? ''"
      :placeholder="field.placeholder"
      :disabled="field.disabled"
      :required="field.required"
      :step="field.step"
      @input="onInput"
      @blur="onBlur"
    >

    <!-- Help text -->
    <div v-if="field.helpText && !hasError" class="form-text">{{ field.helpText }}</div>

    <!-- Errors -->
    <div v-if="hasError" class="invalid-feedback d-block">
      {{ fieldErrors[0] }}
    </div>
  </div>

  <!-- Hidden field -->
  <input v-else type="hidden" :name="field.key" :value="(modelValue as string | number) ?? ''">
</template>

<style scoped>
@media (min-width: 768px) {
  .form-check-aligned {
    min-height: 2.65rem;
    display: flex;
    align-items: center;
  }
}
</style>
