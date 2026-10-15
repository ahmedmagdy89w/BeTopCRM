<script setup lang="ts">
import type { TimelineEvent } from '@/types'

defineProps<{
  event: TimelineEvent
}>()

const ICONS: Record<string, string> = {
  activity: 'bi-telephone',
  status_change: 'bi-arrow-repeat',
  field_update: 'bi-pencil',
  note: 'bi-chat-left-text',
  attachment: 'bi-paperclip',
  task: 'bi-check2-square',
  opportunity: 'bi-currency-dollar',
  email: 'bi-envelope',
  created: 'bi-plus-circle',
  assigned: 'bi-person-check',
  quotation: 'bi-file-earmark-text',
  invoice: 'bi-receipt',
}

const COLORS: Record<string, string> = {
  activity: 'primary',
  status_change: 'warning',
  note: 'info',
  attachment: 'secondary',
  task: 'success',
  opportunity: 'success',
  email: 'primary',
  created: 'info',
  assigned: 'warning',
  quotation: 'primary',
  invoice: 'success',
}
</script>

<template>
  <div class="d-flex gap-3">
    <div class="flex-shrink-0">
      <span
        class="d-inline-flex align-items-center justify-content-center rounded-circle"
        style="width: 36px; height: 36px;"
        :class="`bg-${COLORS[event.type] ?? 'secondary'} bg-opacity-10 text-${COLORS[event.type] ?? 'secondary'}`"
      >
        <i :class="ICONS[event.type] ?? 'bi-circle'"></i>
      </span>
    </div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <strong>{{ event.title }}</strong>
          <span v-if="event.subtype" class="badge bg-light text-dark ms-1">{{ event.subtype }}</span>
        </div>
        <small class="text-muted text-nowrap ms-2">{{ new Date(event.timestamp).toLocaleString() }}</small>
      </div>
      <p v-if="event.description" class="text-muted mb-1 small">{{ event.description }}</p>
      <small class="text-muted">{{ event.user.name }}</small>
    </div>
  </div>
</template>
