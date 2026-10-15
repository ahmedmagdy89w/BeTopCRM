<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/base/BaseModal.vue'

const { t } = useI18n()

defineProps<{
  show: boolean
  message: string
}>()

const emit = defineEmits<{
  accept: []
  cancel: []
}>()
</script>

<template>
  <BaseModal
    :model-value="show"
    :title="t('common.confirm')"
    size="sm"
    :z-index="1060"
    static-backdrop
    @update:model-value="emit('cancel')"
  >
    <p class="mb-0">{{ message }}</p>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="emit('cancel')">
        {{ t('common.no') }}
      </button>
      <button type="button" class="btn btn-danger" @click="emit('accept')">
        {{ t('common.yes') }}
      </button>
    </template>
  </BaseModal>
</template>
