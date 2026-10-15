<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ name?: string; id?: number; size?: number }>()

const initials = computed(() => {
  const n = (props.name as string | undefined ?? '').trim()
  if (!n) return '?'
  const parts = n.split(/\s+/)
  const first = parts[0]?.charAt(0) ?? ''
  const last = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? '' : ''
  return (first + last).toUpperCase().slice(0, 2) || '?'
})

const hue = computed(() => ((props.id ?? 0) * 47) % 360)
</script>

<template>
  <span
    class="user-avatar rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-semibold"
    :title="name ?? ''"
    :style="{ width: `${size ?? 28}px`, height: `${size ?? 28}px`, fontSize: '0.7rem', backgroundColor: `hsl(${hue} 45% 45%)` }"
  >
    {{ initials }}
  </span>
</template>
