<script setup lang="ts">
const { variant = 'primary', pill = false } = defineProps<{
  variant?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark'
  pill?: boolean
}>()
</script>

<template>
  <span :class="['badge', 'badge--centered', `text-bg-${variant}`, { 'rounded-pill': pill }]">
    <slot />
  </span>
</template>
