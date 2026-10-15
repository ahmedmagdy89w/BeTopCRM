<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(defineProps<{
  title?: string
  subtitle?: string
  icon?: string
  collapsible?: boolean
  defaultCollapsed?: boolean
  loading?: boolean
  noPadding?: boolean
}>(), {
  collapsible: false,
  defaultCollapsed: false,
  loading: false,
  noPadding: false,
})

const collapsed = ref(props.defaultCollapsed)

function toggleCollapse() {
  if (props.collapsible) {
    collapsed.value = !collapsed.value
  }
}
</script>

<template>
  <div class="card">
    <div
      v-if="title || $slots['header-actions']"
      class="card-header d-flex align-items-center justify-content-between"
      :class="{ 'cursor-pointer': collapsible }"
      @click="toggleCollapse"
    >
      <div class="d-flex align-items-center gap-2">
        <span v-if="icon" class="card-header__icon"><i :class="['bi', icon]"></i></span>
        <div>
          <h5 class="card-title mb-0">{{ title }}</h5>
          <small v-if="subtitle" class="text-muted">{{ subtitle }}</small>
        </div>
        <i
          v-if="collapsible"
          :class="['bi', collapsed ? 'bi-chevron-down' : 'bi-chevron-up', 'ms-2']"
        ></i>
      </div>
      <div v-if="$slots['header-actions']" @click.stop>
        <slot name="header-actions" />
      </div>
    </div>
    <div v-show="!collapsed" :class="['card-body', { 'p-0': noPadding }]">
      <div v-if="loading" class="d-flex justify-content-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
      <slot v-else />
    </div>
    <div v-if="$slots.footer && !collapsed" class="card-footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
