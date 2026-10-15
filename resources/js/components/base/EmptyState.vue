<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

withDefaults(defineProps<{
  icon?: string
  title?: string
  description?: string
  actionLabel?: string
  actionTo?: Record<string, unknown>
}>(), {
  icon: 'bi-inbox',
  title: '',
  description: '',
  actionLabel: '',
})

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <div class="empty-state text-center py-5">
    <i class="bi d-block mb-3 text-muted" :class="icon" style="font-size: 3rem;"></i>
    <h5 class="text-muted mb-2">{{ title || t('common.noData') }}</h5>
    <p v-if="description" class="text-muted small mb-3">{{ description }}</p>
    <router-link v-if="actionTo && actionLabel" :to="actionTo" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>{{ actionLabel }}
    </router-link>
    <button v-else-if="actionLabel" type="button" class="btn btn-primary btn-sm" @click="emit('action')">
      <i class="bi bi-plus-lg me-1"></i>{{ actionLabel }}
    </button>
  </div>
</template>
