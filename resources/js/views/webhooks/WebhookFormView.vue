<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { useToast } from '@/composables/useToast'
import { webhookService } from '@/services/webhookService'
import type { WebhookDelivery } from '@/services/webhookService'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)

const WEBHOOK_EVENTS = [
  'lead.created', 'lead.updated', 'lead.status_changed', 'lead.assigned', 'lead.deleted',
  'customer.created', 'customer.updated', 'customer.deleted', 'customer.lead_status_changed',
  'customer.assigned', 'customer.converted',
  'opportunity.created', 'opportunity.updated', 'opportunity.deleted', 'opportunity.stage_changed',
  'organization.created', 'organization.updated', 'contact.created', 'task.completed', 'campaign.sent',
]

const form = ref({
  url: '',
  events: [] as string[],
  secret: '',
  is_active: true,
  description: '',
})
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)
const newSecret = ref('')
const created = ref(false)

// Delivery log
const deliveries = ref<WebhookDelivery[]>([])
const deliveriesLoading = ref(false)

async function fetch() {
  if (!id) return
  loading.value = true
  try {
    const wh = await webhookService.get(id)
    form.value = {
      url: wh.url,
      events: [...wh.events],
      secret: '',
      is_active: wh.is_active,
      description: wh.description ?? '',
    }
    await fetchDeliveries()
  } catch {
    toast.error(t('common.somethingWentWrong'))
  } finally {
    loading.value = false
  }
}

async function fetchDeliveries() {
  if (!id) return
  deliveriesLoading.value = true
  try {
    const res = await webhookService.deliveries(id, { per_page: 20 })
    deliveries.value = res.data
  } catch {
    toast.error(t('common.somethingWentWrong'))
  } finally {
    deliveriesLoading.value = false
  }
}

async function handleSubmit() {
  errors.value = {}
  saving.value = true
  try {
    if (isEdit.value && id) {
      await webhookService.update(id, form.value)
    } else {
      const result = await webhookService.create(form.value)
      if (result.secret) {
        newSecret.value = result.secret
        created.value = true
        return
      }
    }
    router.push({ name: 'webhooks.index' })
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    saving.value = false
  }
}

async function handleRotateSecret() {
  if (!id) return
  const ok = await confirm(t('webhooks.confirmRotateSecret'))
  if (!ok) return
  try {
    const result = await webhookService.rotateSecret(id)
    newSecret.value = result.secret
  } catch {
    toast.error(t('common.somethingWentWrong'))
  }
}

async function handleRedeliver(delivery: WebhookDelivery) {
  try {
    await webhookService.redeliver(delivery.id)
    await fetchDeliveries()
  } catch {
    toast.error(t('common.somethingWentWrong'))
  }
}

function eventLabel(event: string): string {
  return t(`webhooks.eventNames.${event}`)
}

function toggleEvent(event: string) {
  const idx = form.value.events.indexOf(event)
  if (idx >= 0) {
    form.value.events.splice(idx, 1)
  } else {
    form.value.events.push(event)
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('webhooks.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <template v-else>
      <form v-if="!created" @submit.prevent="handleSubmit">
        <BaseCard class="mb-3">
          <div class="mb-3">
            <label class="form-label">{{ t('webhooks.url') }}</label>
            <input v-model="form.url" type="url" class="form-control" :class="{ 'is-invalid': errors.url }" placeholder="https://example.com/webhook">
            <div v-if="errors.url" class="invalid-feedback">{{ errors.url[0] }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ t('common.description') }}</label>
            <input v-model="form.description" type="text" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">{{ t('webhooks.secret') }}</label>
            <input
              v-model="form.secret"
              type="password"
              class="form-control"
              :class="{ 'is-invalid': errors.secret }"
              autocomplete="new-password"
              :placeholder="t(isEdit ? 'webhooks.secretPlaceholderEdit' : 'webhooks.secretPlaceholderCreate')"
            >
            <div class="form-text">{{ t(isEdit ? 'webhooks.secretHelpEdit' : 'webhooks.secretHelpCreate') }}</div>
            <div v-if="errors.secret" class="invalid-feedback">{{ errors.secret[0] }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ t('webhooks.events') }}</label>
            <div v-if="errors.events" class="text-danger small mb-1">{{ errors.events[0] }}</div>
            <div class="row">
              <div v-for="event in WEBHOOK_EVENTS" :key="event" class="col-md-4 col-lg-3">
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :id="`ev-${event}`"
                    :checked="form.events.includes(event)"
                    @change="toggleEvent(event)"
                  >
                  <label class="form-check-label small" :for="`ev-${event}`">{{ eventLabel(event) }}</label>
                </div>
              </div>
            </div>
          </div>

          <div class="form-check mb-3">
            <input v-model="form.is_active" class="form-check-input" type="checkbox" id="whActive">
            <label class="form-check-label" for="whActive">{{ t('common.active') }}</label>
          </div>

          <div class="d-flex gap-2">
            <BaseButton type="submit" variant="primary" icon="bi-check-lg" :loading="saving">
              {{ t('common.save') }}
            </BaseButton>
            <BaseButton v-if="isEdit" variant="outline-warning" icon="bi-key" @click="handleRotateSecret">
              {{ t('webhooks.rotateSecret') }}
            </BaseButton>
            <BaseButton variant="outline-secondary" @click="router.back()">
              {{ t('common.cancel') }}
            </BaseButton>
          </div>
        </BaseCard>
      </form>

      <!-- New Secret Alert -->
      <div v-if="newSecret" class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <div>
          <strong>{{ t('webhooks.newSecretWarning') }}</strong>
          <code class="d-block mt-1">{{ newSecret }}</code>
          <BaseButton v-if="created" class="mt-2" variant="outline-secondary" @click="router.push({ name: 'webhooks.index' })">
            {{ t('common.close') }}
          </BaseButton>
        </div>
      </div>

      <!-- Delivery Log -->
      <BaseCard v-if="isEdit" :title="t('webhooks.deliveryLog')" icon="bi-list-check">
        <div v-if="deliveriesLoading" class="text-center py-3"><BaseSpinner /></div>
        <div v-else-if="!deliveries.length" class="text-center text-muted py-3">{{ t('common.noData') }}</div>
        <table v-else class="table table-sm mb-0">
          <thead>
            <tr>
              <th>{{ t('webhooks.event') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('webhooks.responseStatus') }}</th>
              <th>{{ t('webhooks.attempts') }}</th>
              <th>{{ t('common.date') }}</th>
              <th class="text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in deliveries" :key="d.id">
              <td><code class="small">{{ eventLabel(d.event) }}</code></td>
              <td>
                <BaseBadge :variant="d.status === 'success' ? 'success' : d.status === 'pending' ? 'warning' : 'danger'" pill>
                  {{ t(`webhooks.deliveryStates.${d.status}`) }}
                </BaseBadge>
              </td>
              <td>{{ d.response_status ?? '—' }}</td>
              <td>{{ d.attempts }}</td>
              <td>{{ new Date(d.delivered_at ?? d.created_at).toLocaleString() }}</td>
              <td class="text-end">
                <BaseButton
                  v-if="d.status === 'failed'"
                  variant="outline-warning"
                  size="sm"
                  icon="bi-arrow-repeat"
                  @click="handleRedeliver(d)"
                >
                  {{ t('webhooks.redeliver') }}
                </BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </BaseCard>
    </template>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
