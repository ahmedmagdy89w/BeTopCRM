<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, type RouteLocationRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'outline-primary' | 'outline-secondary' | 'outline-success' | 'outline-danger' | 'outline-warning' | 'outline-info' | 'link'
  size?: 'sm' | 'md' | 'lg'
  icon?: string
  iconPosition?: 'start' | 'end'
  loading?: boolean
  disabled?: boolean
  to?: RouteLocationRaw
  type?: 'button' | 'submit' | 'reset'
  block?: boolean
  permission?: string
}>(), {
  variant: 'primary',
  size: 'md',
  iconPosition: 'start',
  loading: false,
  disabled: false,
  type: 'button',
  block: false,
})

defineEmits<{
  click: [event: MouseEvent]
}>()

const router = useRouter()
const auth = useAuthStore()

const visible = computed(() => !props.permission || auth.hasPermission(props.permission))

const cssClass = computed(() => [
  'btn',
  `btn-${props.variant}`,
  props.size !== 'md' ? `btn-${props.size}` : '',
  props.block ? 'w-100' : '',
])

function handleClick(_event: MouseEvent) {
  if (props.to) {
    router.push(props.to)
  }
}
</script>

<template>
  <button
    v-if="visible && !to"
    :type="type"
    :class="cssClass"
    :disabled="disabled || loading"
    @click="($event) => { handleClick($event); $emit('click', $event) }"
  >
    <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    <template v-else>
      <i v-if="icon && iconPosition === 'start'" :class="['bi', icon, 'me-1']"></i>
      <slot name="icon" v-else-if="iconPosition === 'start'" />
    </template>
    <slot />
    <template v-if="!loading">
      <i v-if="icon && iconPosition === 'end'" :class="['bi', icon, 'ms-1']"></i>
      <slot name="icon" v-else-if="iconPosition === 'end'" />
    </template>
  </button>
  <router-link
    v-else-if="visible && to"
    :to="to"
    :class="cssClass"
  >
    <i v-if="icon && iconPosition === 'start'" :class="['bi', icon, 'me-1']"></i>
    <slot />
    <i v-if="icon && iconPosition === 'end'" :class="['bi', icon, 'ms-1']"></i>
  </router-link>
</template>
