<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  score: number
  showLabel?: boolean
}>()

const variant = computed(() => {
  if (props.score >= 70) return 'success'
  if (props.score >= 40) return 'warning'
  return 'danger'
})
</script>

<template>
  <div class="d-flex align-items-center gap-2">
    <div class="progress flex-grow-1" style="height: 8px;">
      <div
        :class="['progress-bar', `bg-${variant}`]"
        role="progressbar"
        :style="{ width: `${Math.min(score, 100)}%` }"
        :aria-valuenow="score"
        aria-valuemin="0"
        aria-valuemax="100"
      ></div>
    </div>
    <small v-if="showLabel" :class="`text-${variant} fw-semibold`">{{ score }}</small>
  </div>
</template>
