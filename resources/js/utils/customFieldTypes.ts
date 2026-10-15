import type { CustomFieldDefinition, CustomFieldType } from '@/types'
import type { FormFieldSchema, FormFieldType } from '@/types/forms'

export interface CustomFieldTypeConfig {
  type: FormFieldType
  step?: number | 'any'
}

export const CUSTOM_FIELD_TYPE_MAP: Record<CustomFieldType, CustomFieldTypeConfig> = {
  text: { type: 'text' },
  textarea: { type: 'textarea' },
  number: { type: 'number', step: 1 },
  decimal: { type: 'number', step: 0.01 },
  date: { type: 'date' },
  datetime: { type: 'datetime' },
  select: { type: 'select' },
  multi_select: { type: 'multi_select' },
  boolean: { type: 'boolean' },
  color: { type: 'color' },
  url: { type: 'url' },
  email: { type: 'email' },
  phone: { type: 'tel' },
}

export function customFieldDefinitionToSchema(definition: CustomFieldDefinition): FormFieldSchema {
  const typeConfig = CUSTOM_FIELD_TYPE_MAP[definition.type]
  const valuesKey = definition.scope === 'category' ? 'category_custom_fields' : 'custom_fields'

  return {
    key: `${valuesKey}.${definition.name}`,
    label: definition.label,
    type: typeConfig.type,
    step: typeConfig.step,
    required: definition.is_required,
    options: definition.options?.map(option => ({ label: option, value: option })),
  }
}

export function customFieldDefinitionsToSchema(definitions: CustomFieldDefinition[]): FormFieldSchema[] {
  return [...definitions]
    .sort((left, right) => left.sort_order - right.sort_order || left.id - right.id)
    .map(customFieldDefinitionToSchema)
}
