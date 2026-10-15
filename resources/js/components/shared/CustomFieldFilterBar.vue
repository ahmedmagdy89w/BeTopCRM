<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomFieldDefinition } from '@/types'
import type { FormFieldSchema } from '@/types/forms'
import { customFieldService } from '@/services/customFieldService'
import { useCustomFieldsStore } from '@/stores/customFields'
import { customFieldDefinitionToSchema } from '@/utils/customFieldTypes'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import FilterControl, { type FilterControlType } from '@/components/shared/FilterControl.vue'

const props = withDefaults(defineProps<{
  entityType: string
  categoryIds?: number[]
  modelValue?: Record<string, unknown>
}>(), {
  categoryIds: () => [],
  modelValue: () => ({}),
})

const emit = defineEmits<{
  change: [key: string, value: unknown]
}>()

const { t } = useI18n()
const store = useCustomFieldsStore()
const categoryDefinitions = ref<CustomFieldDefinition[]>([])
const values = ref<Record<string, unknown>>({ ...props.modelValue })
const loading = ref(false)

const modelDefinitions = computed(() => store.getForEntity(props.entityType).filter(definition => definition.is_filterable))

const filterFields = computed<FormFieldSchema[]>(() => {
  const modelFields = modelDefinitions.value.map((definition) => {
    const schema = customFieldDefinitionToSchema(definition)

    return { ...schema, key: `cf_${definition.name}` }
  })

  const byName = new Map<string, CustomFieldDefinition>()

  for (const definition of categoryDefinitions.value) {
    if (!definition.is_filterable) continue

    const current = byName.get(definition.name)
    const options = Array.from(new Set([...(current?.options ?? []), ...(definition.options ?? [])]))

    byName.set(definition.name, {
      ...definition,
      options,
    })
  }

  const categoryFields = Array.from(byName.values()).map((definition) => {
    const schema = customFieldDefinitionToSchema(definition)

    return { ...schema, key: `ccf_${definition.name}` }
  })

  return [...modelFields, ...categoryFields]
})

async function loadDefinitions(): Promise<void> {
  loading.value = true

  try {
    await store.fetchForEntity(props.entityType)

    if (props.categoryIds.length === 0) {
      categoryDefinitions.value = []

      return
    }

    const groups = await Promise.all(props.categoryIds.map(categoryId => customFieldService.listCategory(props.entityType, categoryId)))
    const definitions = new Map<number, CustomFieldDefinition>()

    for (const group of groups) {
      for (const definition of group.definitions) {
        definitions.set(definition.id, definition)
      }
    }

    categoryDefinitions.value = Array.from(definitions.values())
  } finally {
    loading.value = false
  }
}

function updateFilter(key: string, value: unknown): void {
  values.value = { ...values.value, [key]: value }
  emit('change', key, value)
}

function controlType(field: FormFieldSchema): FilterControlType {
  if (field.type === 'multi_select') return 'multi_select'
  if (field.type === 'select' || field.type === 'number' || field.type === 'date' || field.type === 'datetime') {
    return field.type === 'datetime' ? 'datetime-local' : field.type
  }
  if (field.type === 'email' || field.type === 'tel' || field.type === 'url' || field.type === 'color') return field.type

  return 'text'
}

function optionsFor(field: FormFieldSchema) {
  if (field.type === 'boolean') {
    return [
      { label: t('common.yes'), value: '1' },
      { label: t('common.no'), value: '0' },
    ]
  }

  return field.options ?? []
}

function filterControlType(field: FormFieldSchema): FilterControlType {
  return field.type === 'boolean' ? 'select' : controlType(field)
}

function clearFilters(): void {
  for (const field of filterFields.value) {
    values.value = { ...values.value, [field.key]: null }
    emit('change', field.key, undefined)
  }
}

watch(() => [...props.categoryIds], () => {
  void loadDefinitions()
}, { deep: true })

watch(() => props.modelValue, (value) => {
  values.value = { ...value }
}, { deep: true })

onMounted(loadDefinitions)
</script>

<template>
  <div class="d-flex flex-wrap align-items-end gap-3">
    <FilterControl
      v-for="field in filterFields"
      :key="field.key"
      :label="field.label"
      :type="filterControlType(field)"
      :options="optionsFor(field)"
      :model-value="values[field.key]"
      @update:model-value="updateFilter(field.key, $event)"
    />
    <button v-if="filterFields.length" type="button" class="btn btn-link btn-sm" @click="clearFilters">
      {{ t('common.clearFilters') }}
    </button>
    <div class="custom-field-filter-bar__loading" :aria-hidden="!loading">
      <BaseSpinner v-if="loading" size="sm" />
    </div>
  </div>
</template>

<style scoped>
.custom-field-filter-bar__loading {
  display: flex;
  flex: 0 0 1.5rem;
  width: 1.5rem;
  height: 1.5rem;
  align-items: center;
  justify-content: center;
}
</style>
