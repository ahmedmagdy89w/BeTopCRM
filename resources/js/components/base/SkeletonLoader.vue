<script setup lang="ts">
withDefaults(defineProps<{
  width?: string
  height?: string
  rounded?: boolean
  circle?: boolean
}>(), {
  width: '100%',
  height: '1rem',
  rounded: true,
  circle: false,
})
</script>

<template>
  <div
    class="skeleton-loader"
    :class="{ 'rounded-pill': rounded, 'rounded-circle': circle }"
    :style="{ width, height }"
  ></div>
</template>

<style scoped>
.skeleton-loader {
  background: linear-gradient(90deg, var(--bs-gray-200) 25%, var(--bs-gray-100) 50%, var(--bs-gray-200) 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
