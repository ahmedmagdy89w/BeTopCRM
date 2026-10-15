<script setup lang="ts">
import type { Tag } from '@/types'

defineProps<{
  tags: Tag[]
  removable?: boolean
}>()

const emit = defineEmits<{
  remove: [tagId: number]
}>()
</script>

<template>
  <div class="d-flex flex-wrap gap-1">
    <span
      v-for="tag in tags"
      :key="tag.id"
      class="badge badge--centered tag-badge rounded-pill d-inline-flex align-items-center gap-1"
      :style="{ backgroundColor: tag.color || '#6c757d', color: '#fff' }"
    >
      {{ tag.name }}
      <button
        v-if="removable"
        type="button"
        class="btn-close btn-close-white"
        style="font-size: 0.5em;"
        @click="emit('remove', tag.id)"
      ></button>
    </span>
    <span v-if="tags.length === 0" class="text-muted small">—</span>
  </div>
</template>
