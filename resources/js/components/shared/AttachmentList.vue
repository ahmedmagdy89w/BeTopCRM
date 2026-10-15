<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { attachmentService } from '@/services/attachmentService'
import type { Attachment } from '@/types'
import FormFileUpload from '@/components/form/FormFileUpload.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const props = defineProps<{
  entityType: string
  entityId: number
  canUpload?: boolean
}>()

const emit = defineEmits<{
  uploaded: [attachments: Attachment[]]
  deleted: [attachment: Attachment]
}>()

const attachments = ref<Attachment[]>([])
const loading = ref(true)
const uploading = ref(false)
const previewAttachment = ref<Attachment | null>(null)
const showPreview = ref(false)

function isPreviewable(mimeType: string): boolean {
  return mimeType === 'application/pdf' || mimeType.startsWith('image/')
}

function isImage(mimeType: string): boolean {
  return mimeType.startsWith('image/')
}

function openPreview(attachment: Attachment) {
  previewAttachment.value = attachment
  showPreview.value = true
}

const MIME_ICONS: Record<string, string> = {
  'application/pdf': 'bi-file-earmark-pdf text-danger',
  'application/msword': 'bi-file-earmark-word text-primary',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'bi-file-earmark-word text-primary',
  'application/vnd.ms-excel': 'bi-file-earmark-excel text-success',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'bi-file-earmark-excel text-success',
  'image/jpeg': 'bi-file-earmark-image text-info',
  'image/png': 'bi-file-earmark-image text-info',
  'image/gif': 'bi-file-earmark-image text-info',
  'text/plain': 'bi-file-earmark-text text-secondary',
  'text/csv': 'bi-file-earmark-spreadsheet text-success',
}

function getIcon(mimeType: string): string {
  return MIME_ICONS[mimeType] || 'bi-file-earmark text-secondary'
}

async function fetchAttachments() {
  loading.value = true
  try {
    attachments.value = await attachmentService.list(props.entityType, props.entityId)
  } finally {
    loading.value = false
  }
}

async function handleUpload(files: File[]) {
  uploading.value = true
  try {
    const uploaded = await attachmentService.upload(props.entityType, props.entityId, files)
    attachments.value.push(...uploaded)
    emit('uploaded', uploaded)
  } finally {
    uploading.value = false
  }
}

async function handleDelete(attachment: Attachment) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await attachmentService.delete(attachment.id)
  attachments.value = attachments.value.filter(a => a.id !== attachment.id)
  emit('deleted', attachment)
}

onMounted(fetchAttachments)
</script>

<template>
  <div>
    <div v-if="canUpload" class="mb-3">
      <FormFileUpload :loading="uploading" @upload="handleUpload" />
    </div>

    <div v-if="loading" class="text-center py-3">
      <BaseSpinner />
    </div>

    <div v-else-if="attachments.length === 0" class="text-center text-muted py-3">
      {{ t('common.noData') }}
    </div>

    <div v-else class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>File</th>
            <th>Size</th>
            <th>Uploaded By</th>
            <th>Date</th>
            <th class="text-end">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="att in attachments" :key="att.id">
            <td>
              <i :class="getIcon(att.mime_type)" class="me-2"></i>
              {{ att.original_name }}
            </td>
            <td><small class="text-muted">{{ att.size_human }}</small></td>
            <td>{{ att.uploaded_by.name }}</td>
            <td>{{ new Date(att.created_at).toLocaleDateString() }}</td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <button
                  v-if="isPreviewable(att.mime_type)"
                  type="button"
                  class="btn btn-outline-secondary btn-sm"
                  :title="t('common.view')"
                  :aria-label="t('common.view')"
                  @click="openPreview(att)"
                >
                  <i class="bi bi-eye"></i>
                </button>
                <a
                  :href="attachmentService.download(att.id)"
                  class="btn btn-outline-primary btn-sm"
                  target="_blank"
                  rel="noopener"
                >
                  <i class="bi bi-download"></i>
                </a>
                <button
                  v-if="canUpload"
                  class="btn btn-outline-danger btn-sm"
                  @click="handleDelete(att)"
                >
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />

    <BaseModal v-model="showPreview" :title="previewAttachment?.original_name ?? ''" size="xl" scrollable>
      <div v-if="previewAttachment" class="text-center">
        <img
          v-if="isImage(previewAttachment.mime_type)"
          :src="attachmentService.download(previewAttachment.id)"
          :alt="previewAttachment.original_name"
          class="img-fluid rounded border"
        >
        <iframe
          v-else
          :src="attachmentService.download(previewAttachment.id)"
          :title="previewAttachment.original_name"
          class="attachment-preview-frame"
        />
      </div>
      <template #footer>
        <button type="button" class="btn btn-secondary btn-sm" @click="showPreview = false">
          {{ t('common.close') }}
        </button>
        <a
          v-if="previewAttachment"
          :href="attachmentService.download(previewAttachment.id)"
          class="btn btn-outline-primary btn-sm"
          target="_blank"
          rel="noopener"
        >
          <i class="bi bi-download me-1"></i>{{ t('common.download') }}
        </a>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.attachment-preview-frame {
  width: 100%;
  min-height: 70vh;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.65rem;
  background: var(--bs-body-bg);
}
</style>
