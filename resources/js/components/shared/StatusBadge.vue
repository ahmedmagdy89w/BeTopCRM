<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  LEAD_STATUS_COLORS,
  CUSTOMER_STATUS_COLORS,
  TASK_STATUS_COLORS,
  TASK_PRIORITY_COLORS,
} from '@/utils/constants'

const { t } = useI18n()

const props = defineProps<{
  status: string
  type: 'lead' | 'customer' | 'task' | 'taskPriority'
}>()

const COLOR_MAPS: Record<string, Record<string, string>> = {
  lead: LEAD_STATUS_COLORS,
  customer: CUSTOMER_STATUS_COLORS,
  task: TASK_STATUS_COLORS,
  taskPriority: TASK_PRIORITY_COLORS,
}

const LABEL_KEYS: Record<string, string> = {
  lead: 'leads.statuses',
  customer: 'customers.statuses',
  task: 'tasks.statuses',
  taskPriority: 'tasks.priorities',
}

const variant = computed(() => {
  const map = COLOR_MAPS[props.type]
  return map?.[props.status] ?? 'secondary'
})

const label = computed(() => {
  const key = `${LABEL_KEYS[props.type]}.${props.status}`
  const translated = t(key)
  // If no translation found, format the status nicely
  return translated === key
    ? props.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
    : translated
})
</script>

<template>
  <span :class="['badge', 'badge--centered', `text-bg-${variant}`]">{{ label }}</span>
</template>
