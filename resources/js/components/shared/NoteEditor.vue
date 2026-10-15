<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  loading?: boolean
  parentId?: number
  placeholder?: string
}>()

const emit = defineEmits<{
  submit: [body: string, parentId?: number]
}>()

const body = ref('')

function handleSubmit() {
  if (!body.value.trim()) return
  emit('submit', body.value.trim())
  body.value = ''
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="d-flex gap-2">
    <textarea
      v-model="body"
      class="form-control form-control-sm"
      :placeholder="placeholder || t('common.notes') + '...'"
      rows="2"
      required
    ></textarea>
    <div class="d-flex flex-column gap-1">
      <button type="submit" class="btn btn-primary btn-sm" :disabled="loading || !body.trim()">
        <span v-if="loading" class="spinner-border spinner-border-sm"></span>
        <i v-else class="bi bi-send"></i>
      </button>
    </div>
  </form>
</template>
