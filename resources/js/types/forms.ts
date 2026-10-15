import type { ZodType } from 'zod'

export interface SelectOption {
  label: string
  value: string | number
}

export interface QuickAddConfig {
  title: string
  fields: FormFieldSchema[]
  service: string
  permission?: string
}

export type FormFieldType =
  | 'text'
  | 'email'
  | 'tel'
  | 'number'
  | 'color'
  | 'textarea'
  | 'select'
  | 'radio'
  | 'multi_select'
  | 'date'
  | 'datetime'
  | 'boolean'
  | 'file'
  | 'tag'
  | 'url'
  | 'password'
  | 'hidden'
  | 'user'

export interface FormFieldSchema {
  key: string
  label: string
  type: FormFieldType
  required?: boolean
  placeholder?: string
  helpText?: string
  disabled?: boolean
  visible?: boolean | ((values: Record<string, unknown>) => boolean)
  rules?: ZodType
  colSpan?: 1 | 2 | 3
  min?: string
  max?: string

  // Select-specific
  options?: SelectOption[]
  optionsUrl?: string
  optionsLabelKey?: string
  optionsValueKey?: string

  // Quick-add relationship
  quickAdd?: QuickAddConfig
  step?: number | 'any'
  multiple?: boolean
}

export type FormErrors = Record<string, string[]>

export interface DataTableColumn {
  key: string
  label: string
  sortable?: boolean
  filterable?: boolean
  filterOptions?: SelectOption[]
  type?: 'text' | 'date' | 'currency' | 'badge' | 'custom'
  align?: 'start' | 'center' | 'end'
  width?: string
  visible?: boolean
}
