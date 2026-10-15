<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { whatsappService, type PreparedWhatsAppMessage, type WhatsAppEntityType } from '@/services/whatsappService'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps<{
  modelValue: boolean
  entityType: WhatsAppEntityType
  customerId: number
  opportunityId?: number
  quotationId?: number
  invoiceId?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  sent: []
}>()

const { t } = useI18n()
const toast = useToast()
const prepared = ref<PreparedWhatsAppMessage | null>(null)
const phone = ref('')
const message = ref('')
const useCloudApi = ref(false)
const loading = ref(false)
const sending = ref(false)

watch(() => props.modelValue, async (isOpen) => {
  if (!isOpen) return

  loading.value = true
  prepared.value = null
  try {
    const result = await whatsappService.prepare({
      entity_type: props.entityType,
      customer_id: props.customerId,
      ...(props.opportunityId ? { opportunity_id: props.opportunityId } : {}),
      ...(props.quotationId ? { quotation_id: props.quotationId } : {}),
      ...(props.invoiceId ? { invoice_id: props.invoiceId } : {}),
    })
    prepared.value = result
    phone.value = result.phone
    message.value = result.message
    useCloudApi.value = false
  } catch {
    toast.error(t('whatsapp.prepareFailed'))
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
})

function documentSubject(): string {
  if (props.entityType === 'quotation') return t('whatsapp.quotationSubject', { number: prepared.value?.document_number ?? '' })
  if (props.entityType === 'invoice') return t('whatsapp.invoiceSubject', { number: prepared.value?.document_number ?? '' })
  return t('whatsapp.customerSubject', { name: prepared.value?.recipient_name ?? '' })
}

function normalizedPhone(): string {
  const digits = phone.value.replace(/\D/g, '')

  return digits.startsWith('00') ? digits.slice(2) : digits
}

async function sendMessage() {
  if (!prepared.value || !phone.value.trim() || !message.value.trim()) return

  sending.value = true
  try {
    if (useCloudApi.value && prepared.value.cloud_api_available) {
      await whatsappService.send({
        customer_id: props.customerId,
        ...(props.opportunityId ? { opportunity_id: props.opportunityId } : {}),
        phone: phone.value,
        message: message.value,
        subject: documentSubject(),
        via_cloud_api: true,
      })
      toast.success(t('whatsapp.sent'))
    } else {
      window.open(
        `https://wa.me/${encodeURIComponent(normalizedPhone())}?text=${encodeURIComponent(message.value)}`,
        '_blank',
        'noopener,noreferrer',
      )
      await whatsappService.send({
        customer_id: props.customerId,
        ...(props.opportunityId ? { opportunity_id: props.opportunityId } : {}),
        phone: phone.value,
        message: message.value,
        subject: documentSubject(),
      })
      toast.success(t('whatsapp.opened'))
    }

    emit('sent')
    emit('update:modelValue', false)
  } catch {
    toast.error(t('whatsapp.sendFailed'))
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('whatsapp.title')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="loading" class="text-center py-5">
      <span class="spinner-border spinner-border-sm me-2" role="status"></span>
      {{ t('common.loading') }}
    </div>
    <div v-else-if="prepared" class="vstack gap-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-whatsapp fs-4 text-success" aria-hidden="true"></i>
        <div>
          <div class="fw-semibold">{{ prepared.recipient_name }}</div>
          <div class="small text-muted">{{ t('whatsapp.to') }}</div>
        </div>
      </div>

      <div>
        <label class="form-label" for="whatsapp-phone">{{ t('common.phone') }}</label>
        <input id="whatsapp-phone" v-model="phone" type="tel" class="form-control" autocomplete="tel" dir="ltr">
        <div class="form-text">{{ t('whatsapp.phoneHint') }}</div>
      </div>

      <div>
        <label class="form-label" for="whatsapp-message">{{ t('whatsapp.message') }}</label>
        <textarea id="whatsapp-message" v-model="message" class="form-control" rows="9" maxlength="4000"></textarea>
        <div class="form-text text-end">{{ message.length }}/4000</div>
      </div>

      <a v-if="prepared.pdf_url" :href="prepared.pdf_url" target="_blank" rel="noopener noreferrer" class="small">
        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>{{ t('whatsapp.documentLink') }}
      </a>

      <label v-if="prepared.cloud_api_available" class="form-check">
        <input v-model="useCloudApi" class="form-check-input" type="checkbox">
        <span class="form-check-label">{{ t('whatsapp.sendFromCrm') }}</span>
      </label>
    </div>
    <template #footer>
      <BaseButton variant="secondary" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</BaseButton>
      <BaseButton
        v-if="prepared"
        variant="success"
        icon="bi-whatsapp"
        :loading="sending"
        :disabled="!phone.trim() || !message.trim()"
        @click="sendMessage"
      >
        {{ useCloudApi ? t('whatsapp.send') : t('whatsapp.openWhatsApp') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>