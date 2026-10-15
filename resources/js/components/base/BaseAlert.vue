<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark'
  dismissible?: boolean
  icon?: string
}>(), {
  variant: 'info',
  dismissible: false,
})

const emit = defineEmits<{
  dismiss: []
}>()
</script>

<template>
  <div
    :class="[
      'alert',
      `alert-${variant}`,
      { 'alert-dismissible fade show': dismissible },
      'd-flex align-items-center',
    ]"
    role="alert"
  >
    <i v-if="icon" :class="['bi', icon, 'me-2']"></i>
    <div class="flex-grow-1">
      <slot />
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="btn-close"
      @click="emit('dismiss')"
      aria-label="Close"
    ></button>
  </div>
</template>
