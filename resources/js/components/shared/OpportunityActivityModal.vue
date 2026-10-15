<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { isAxiosError } from 'axios'
import { opportunityActivityService, type OpportunityActivityPayload } from '@/services/opportunityActivityService'
import type { OpportunityActivity } from '@/types'
import type { ActivityChannel } from '@/types/enums'
import type { OpportunityActivityType } from '@/types/models'
import BaseModal from '@/components/base/BaseModal.vue'

const props = defineProps<{
  modelValue: boolean
  customerId: number
  opportunityId: number
  activity?: OpportunityActivity | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [activity: OpportunityActivity]
}>()

const { t } = useI18n()

const ACTIVITY_TYPES: OpportunityActivityType[] = ['call', 'email', 'meeting', 'task', 'whatsapp', 'note']
const CHANNELS: ActivityChannel[] = ['call', 'email', 'meeting', 'whatsapp', 'in_person', 'other']

const TYPE_ICONS: Record<OpportunityActivityType, string> = {
  call: 'bi-telephone',
  email: 'bi-envelope',
  meeting: 'bi-people',
  task: 'bi-list-task',
  whatsapp: 'bi-whatsapp',
  note: 'bi-sticky',
}

const form = ref({
  type: 'call' as OpportunityActivityType,
  channel: '' as ActivityChannel | '',
  subject: '',
  description: '',
  due_date: '',
  reminder_at: '',
})
const saving = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEdit = computed(() => !!props.activity)

function toInputValue(iso?: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function toIsoString(local: string): string | undefined {
  if (!local) return undefined
  return new Date(local).toISOString()
}

function resetForm() {
  const source = props.activity
  form.value = {
    type: source?.type ?? 'call',
    channel: source?.channel ?? '',
    subject: source?.subject ?? '',
    description: source?.description ?? '',
    due_date: toInputValue(source?.due_date),
    reminder_at: toInputValue(source?.reminder_at),
  }
  error.value = ''
  fieldErrors.value = {}
}

watch(() => props.modelValue, (open) => {
  if (open) resetForm()
})

function parseLocal(value: string): number | null {
  if (!value) return null
  const time = new Date(value).getTime()
  return Number.isNaN(time) ? null : time
}

const reminderAfterDue = computed(() => {
  const due = parseLocal(form.value.due_date)
  const reminder = parseLocal(form.value.reminder_at)
  if (due === null || reminder === null) return false
  return reminder > due
})

const reminderInPast = computed(() => {
  const reminder = parseLocal(form.value.reminder_at)
  if (reminder === null) return false
  return reminder <= Date.now()
})

const minReminder = computed(() => toInputValue(new Date().toISOString()))

const hasDateError = computed(() => reminderAfterDue.value || reminderInPast.value)

const canSave = computed(() => {
  if (!form.value.subject.trim() || saving.value) return false
  if (hasDateError.value) return false
  return true
})

function close() {
  emit('update:modelValue', false)
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    const payload: OpportunityActivityPayload = {
      type: form.value.type,
      subject: form.value.subject.trim(),
    }
    if (form.value.channel) payload.channel = form.value.channel
    if (form.value.description.trim()) payload.description = form.value.description.trim()
    const dueDate = toIsoString(form.value.due_date)
    const reminderAt = toIsoString(form.value.reminder_at)
    if (dueDate) payload.due_date = dueDate
    if (reminderAt) payload.reminder_at = reminderAt

    const saved = props.activity
      ? await opportunityActivityService.update(props.customerId, props.opportunityId, props.activity.id, payload)
      : await opportunityActivityService.create(props.customerId, props.opportunityId, payload)
    emit('saved', saved)
    close()
  } catch (err: unknown) {
    if (isAxiosError(err) && err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
    } else {
      error.value = t('common.error')
    }
  } finally {
    saving.value = false
  }
}

</script>

<template>
  <BaseModal :model-value="modelValue" :title="t('customers.logActivity')" size="lg" @update:model-value="close">
    <!-- Interaction -->
    <h6 class="text-uppercase text-muted small fw-semibold mb-2">{{ t('customers.activityInteraction') }}</h6>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button
        v-for="type in ACTIVITY_TYPES"
        :key="type"
        type="button"
        class="btn btn-sm d-flex flex-column align-items-center justify-content-center gap-1"
        :class="form.type === type ? 'btn-primary' : 'btn-outline-secondary'"
        style="min-width: 72px; padding: 0.5rem 0.75rem"
        @click="form.type = type"
      >
        <i :class="['bi fs-5', TYPE_ICONS[type]]"></i>
        <span style="font-size: 0.7rem; line-height: 1">{{ t(`opportunities.activityTypes.${type}`) }}</span>
      </button>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12">
        <label class="form-label small fw-semibold mb-1">{{ t('customers.activitySubject') }} <span class="text-danger">*</span></label>
        <input v-model="form.subject" type="text" class="form-control" :placeholder="t('customers.activitySubject')" />
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ t('customers.activityChannel') }}</label>
        <select v-model="form.channel" class="form-select">
          <option value="">—</option>
          <option v-for="channel in CHANNELS" :key="channel" :value="channel">
            {{ t(`customers.activityChannels.${channel}`) }}
          </option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label small fw-semibold mb-1">{{ t('customers.activityDescription') }}</label>
        <textarea v-model="form.description" class="form-control" rows="3"></textarea>
      </div>
    </div>

    <!-- Next step -->
    <h6 class="text-uppercase text-muted small fw-semibold mb-2">{{ t('customers.activityNextStep') }}</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ t('customers.activityDueDate') }}</label>
        <input v-model="form.due_date" type="datetime-local" class="form-control" :class="{ 'is-invalid': fieldErrors.due_date }" />
        <div v-if="fieldErrors.due_date" class="invalid-feedback d-block">{{ fieldErrors.due_date[0] }}</div>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ t('customers.activityReminderAt') }}</label>
        <input v-model="form.reminder_at" type="datetime-local" class="form-control" :min="minReminder" :max="form.due_date || undefined" :class="{ 'is-invalid': hasDateError || fieldErrors.reminder_at }" />
        <div v-if="reminderAfterDue" class="invalid-feedback d-block">{{ t('customers.activityReminderAfterDue') }}</div>
        <div v-else-if="reminderInPast" class="invalid-feedback d-block">{{ t('customers.activityReminderInPast') }}</div>
        <div v-else-if="fieldErrors.reminder_at" class="invalid-feedback d-block">{{ fieldErrors.reminder_at[0] }}</div>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>

    <template #footer>
      <button type="button" class="btn btn-outline-secondary" @click="close">
        {{ t('common.cancel') }}
      </button>
      <button type="button" class="btn btn-primary" :disabled="!canSave" @click="save">
        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="bi bi-send me-1"></i>
        {{ isEdit ? t('common.save') : t('customers.logActivity') }}
      </button>
    </template>
  </BaseModal>
</template>
