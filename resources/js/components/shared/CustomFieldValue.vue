<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomFieldDefinition } from '@/types'

const props = defineProps<{
  definition: CustomFieldDefinition
  value: unknown
}>()

const { t } = useI18n()

const displayValue = computed(() => {
  if (props.value === null || props.value === undefined || props.value === '') return '—'

  if (props.definition.type === 'boolean') {
    return props.value ? t('common.yes') : t('common.no')
  }

  if (props.definition.type === 'multi_select' && Array.isArray(props.value)) {
    return props.value.join(', ')
  }

  if (props.definition.type === 'date' && typeof props.value === 'string') {
    const date = new Date(`${props.value}T00:00:00`)

    return Number.isNaN(date.getTime()) ? props.value : date.toLocaleDateString()
  }

  return String(props.value)
})
</script>

<template>
  <a v-if="definition.type === 'url' && value" :href="String(value)" target="_blank" rel="noopener noreferrer">
    {{ displayValue }}
  </a>
  <a v-else-if="definition.type === 'email' && value" :href="`mailto:${String(value)}`">
    {{ displayValue }}
  </a>
  <span v-else>{{ displayValue }}</span>
</template>
