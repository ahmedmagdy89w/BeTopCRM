<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  score: number
}>()

const variant = computed(() => {
  if (props.score >= 80) return 'success'
  if (props.score >= 50) return 'warning'
  if (props.score >= 20) return 'info'
  return 'secondary'
})
</script>

<template>
  <span :class="['badge', 'badge--centered', `text-bg-${variant}`]">
    <i class="bi bi-star-fill me-1"></i>{{ score }}
  </span>
</template>
