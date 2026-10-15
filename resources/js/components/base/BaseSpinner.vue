<script setup lang="ts">
withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg'
  variant?: 'primary' | 'secondary' | 'light'
}>(), {
  size: 'md',
  variant: 'primary',
})
</script>

<template>
  <div class="d-flex justify-content-center align-items-center" role="status">
    <div
      :class="[
        'spinner-border',
        `text-${variant}`,
        { 'spinner-border-sm': size === 'sm' },
      ]"
      :style="size === 'lg' ? 'width: 3rem; height: 3rem;' : undefined"
    >
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
</template>
