<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { templateService } from '@/services/templateService'
import { asyncJobService } from '@/services/asyncJobService'
import type { GenerateTemplateParams, GenerateTemplateResult } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import suneditor from 'suneditor'
import plugins from 'suneditor/src/plugins'
import 'suneditor/dist/css/suneditor.min.css'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)

// ── Main form ────────────────────────────────────────────────────────────────
const form = ref({ name: '', subject: '', body: '' })
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)
const showPreview = ref(false)
const previewHtml = ref('')
const showVariables = ref(false)
const copiedVar = ref('')

// ── AI panel ─────────────────────────────────────────────────────────────────
const showAiPanel = ref(false)
const aiLoading = ref(false)
const aiSuccess = ref(false)
const aiErrors = ref<Record<string, string[]>>({})
const aiForm = reactive<GenerateTemplateParams>({
  topic: '',
  audience: '',
  tone: 'professional',
  language: locale.value === 'ar' ? 'ar' : 'en',
  context: '',
})

let aiPollTimer: ReturnType<typeof setInterval> | null = null

function stopAiPolling() {
  aiLoading.value = false
  if (aiPollTimer) {
    clearInterval(aiPollTimer)
    aiPollTimer = null
  }
}

function applyAiResult(result: GenerateTemplateResult) {
  form.value.subject = result.subject
  form.value.body = result.body
  if (editorInstance) {
    editorInstance.setContents(result.body)
  }
  aiSuccess.value = true
  toast.success(t('templates.ai.success'))
}

async function generateWithAi() {
  aiErrors.value = {}
  aiSuccess.value = false
  aiLoading.value = true
  try {
    const job = await templateService.generate(aiForm)
    aiPollTimer = setInterval(async () => {
      try {
        const polled = await asyncJobService.get(job.id)
        if (polled.status === 'completed') {
          stopAiPolling()
          applyAiResult(polled.result as GenerateTemplateResult)
        } else if (polled.status === 'failed') {
          stopAiPolling()
          aiErrors.value = { ai: [polled.error ?? t('templates.ai.errorAi')] }
        }
      } catch {
        stopAiPolling()
        aiErrors.value = { ai: [t('templates.ai.errorAi')] }
      }
    }, 3000)
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      aiErrors.value = e.response.data.errors
    } else {
      aiErrors.value = { ai: [t('templates.ai.errorAi')] }
    }
    aiLoading.value = false
  }
}

// ── Template variables ────────────────────────────────────────────────────────
function copyVariable(variable: string) {
  navigator.clipboard.writeText(variable)
  copiedVar.value = variable
  setTimeout(() => { copiedVar.value = '' }, 2000)
}

const TEMPLATE_VARIABLES = [
  { variable: '{{contact_name}}', sourceKey: 'varContactName', source: 'customer.contact_name / primary contact / customer name' },
  { variable: '{{contact_email}}', sourceKey: 'varContactEmail', source: 'campaign recipient email / customer.email' },
  { variable: '{{contact_phone}}', sourceKey: 'varContactPhone', source: 'customer.phone / primary contact phone' },
  { variable: '{{company_name}}', sourceKey: 'varCompanyName', source: 'customer.company_name' },
  { variable: '{{account_manager_name}}', sourceKey: 'varAccountManager', source: 'customer.accountManager.name' },
  { variable: '{{deal_value}}', sourceKey: 'varDealValue', source: 'customer.opportunities[].amount' },
]

// ── Data loading ──────────────────────────────────────────────────────────────
async function fetch() {
  if (!id) return
  loading.value = true
  try {
    const tpl = await templateService.get(id)
    form.value = { name: tpl.name, subject: tpl.subject, body: tpl.body }
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  errors.value = {}
  // SunEditor's change event does not reliably fire for plain typing, so
  // pull the current contents at submit time — otherwise body stays empty
  // and every template save fails validation.
  if (editorInstance) {
    form.value.body = editorInstance.getContents()
  }
  saving.value = true
  try {
    if (isEdit.value && id) {
      await templateService.update(id, form.value)
    } else {
      await templateService.create(form.value)
    }
    router.push({ name: 'templates.index' })
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

// ── SunEditor ─────────────────────────────────────────────────────────────────
const editorRef = ref<HTMLTextAreaElement | null>(null)
let editorInstance: any = null

onMounted(async () => {
  await fetch()

    if (editorRef.value) {
      editorInstance = suneditor.create(editorRef.value, {
        plugins: plugins,
        font: ['Arial', 'Verdana', 'Georgia', 'Courier New', 'Tahoma'],
        minHeight: '300px',
        // NOTE: onChange must be registered here at creation — assigning
        // editorInstance.onChange afterwards has no effect at runtime, so the
        // template body would never sync and every save would 422.
        ...({
          onChange: (contents: string) => {
            form.value.body = contents
          },
        } as Record<string, unknown>),
      buttonList: [
        ['undo', 'redo'],
        ['font', 'fontSize', 'formatBlock'],
        ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
        ['removeFormat'],
        '/',
        ['fontColor', 'hiliteColor', 'outdent', 'indent', 'align', 'horizontalRule', 'list', 'table'],
        ['link', 'image', 'video'],
        ['fullScreen', 'showBlocks', 'codeView'],
        ['preview']
      ]
    })

    if (form.value.body) {
      editorInstance.setContents(form.value.body)
    }
  }
})

onBeforeUnmount(() => {
  stopAiPolling()
  if (editorInstance) {
    editorInstance.destroy()
  }
})
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('templates.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <!-- ── AI Email Generator Panel ─────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
      <!-- Panel header (toggle) -->
      <div
        class="card-header bg-white d-flex align-items-center justify-content-between py-3"
        role="button"
        style="cursor:pointer;"
        @click="showAiPanel = !showAiPanel"
      >
        <div class="d-flex align-items-center gap-2">
          <span
            class="rounded-circle d-flex align-items-center justify-content-center"
            style="width:34px;height:34px;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);"
          >
            <i class="bi bi-stars text-white" style="font-size:.9rem;"></i>
          </span>
          <span class="fw-semibold text-body">{{ t('templates.ai.panelTitle') }}</span>
          <span
            class="badge fw-semibold px-2"
            style="font-size:.7rem;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);"
          >{{ t('templates.ai.badge') }}</span>
        </div>
        <i class="bi text-secondary" :class="showAiPanel ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </div>

      <!-- Panel body -->
      <div v-show="showAiPanel" class="card-body pt-3">
        <p class="text-muted small mb-4">{{ t('templates.ai.panelDesc') }}</p>

        <!-- Success alert -->
        <div v-if="aiSuccess" class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
          <i class="bi bi-check-circle-fill flex-shrink-0"></i>
          <span class="small">{{ t('templates.ai.success') }}</span>
        </div>

        <!-- Polling progress banner -->
        <div v-if="aiLoading" class="alert d-flex align-items-center gap-2 py-2 mb-3 border-0" style="background:linear-gradient(135deg,#6366f110 0%,#8b5cf610 100%);" role="status">
          <span class="spinner-border spinner-border-sm flex-shrink-0" style="color:#8b5cf6;" aria-hidden="true"></span>
          <span class="small fw-medium" style="color:#6366f1;">{{ t('templates.ai.generating') }}</span>
        </div>

        <!-- Error alert (AI provider failure) -->
        <div v-if="aiErrors.ai" class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="small">{{ aiErrors.ai[0] }}</span>
        </div>

        <div class="row g-3">
          <!-- Topic (required) -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">
              {{ t('templates.ai.topic') }}
              <span class="text-danger">*</span>
            </label>
            <input
              v-model="aiForm.topic"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': aiErrors.topic }"
              :placeholder="t('templates.ai.topicPlaceholder')"
              maxlength="500"
            >
            <div v-if="aiErrors.topic" class="invalid-feedback">{{ aiErrors.topic[0] }}</div>
          </div>

          <!-- Audience -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">{{ t('templates.ai.audience') }}</label>
            <input
              v-model="aiForm.audience"
              type="text"
              class="form-control"
              :placeholder="t('templates.ai.audiencePlaceholder')"
              maxlength="255"
            >
          </div>

          <!-- Tone -->
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label fw-medium">{{ t('templates.ai.tone') }}</label>
            <select v-model="aiForm.tone" class="form-select">
              <option value="professional">{{ t('templates.ai.tones.professional') }}</option>
              <option value="friendly">{{ t('templates.ai.tones.friendly') }}</option>
              <option value="urgent">{{ t('templates.ai.tones.urgent') }}</option>
            </select>
          </div>

          <!-- Language -->
          <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label fw-medium">{{ t('templates.ai.language') }}</label>
            <select v-model="aiForm.language" class="form-select">
              <option value="en">{{ t('templates.ai.languages.en') }}</option>
              <option value="ar">{{ t('templates.ai.languages.ar') }}</option>
            </select>
          </div>

          <!-- Context -->
          <div class="col-12">
            <label class="form-label fw-medium">{{ t('templates.ai.context') }}</label>
            <textarea
              v-model="aiForm.context"
              class="form-control"
              rows="3"
              :placeholder="t('templates.ai.contextPlaceholder')"
              maxlength="1000"
            ></textarea>
          </div>
        </div>

        <!-- Generate button -->
        <div class="mt-3 d-flex justify-content-end">
          <button
            type="button"
            class="btn d-inline-flex align-items-center gap-2 text-white fw-semibold"
            style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);"
            :disabled="aiLoading"
            @click="generateWithAi"
          >
            <span v-if="aiLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <i v-else class="bi bi-stars"></i>
            {{ aiLoading ? t('templates.ai.generating') : t('templates.ai.generateBtn') }}
          </button>
        </div>
      </div>
    </div>
    <!-- ── /AI Panel ─────────────────────────────────────────────────────── -->

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <form v-else @submit.prevent="handleSubmit">
      <BaseCard>
        <div class="mb-3">
          <label class="form-label">{{ t('templates.name') }}</label>
          <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }">
          <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ t('templates.subject') }}</label>
          <input v-model="form.subject" type="text" class="form-control" :class="{ 'is-invalid': errors.subject }">
          <div v-if="errors.subject" class="invalid-feedback">{{ errors.subject[0] }}</div>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ t('templates.body') }}</label>
          <div :class="{ 'is-invalid border border-danger rounded': errors.body }">
            <textarea ref="editorRef" style="display:none;"></textarea>
          </div>
          <div v-if="errors.body" class="invalid-feedback d-block">{{ errors.body[0] }}</div>
        </div>

        <div class="d-flex gap-2">
          <BaseButton type="submit" variant="primary" icon="bi-check-lg" :loading="saving">
            {{ t('common.save') }}
          </BaseButton>
          <BaseButton variant="outline-secondary" @click="router.back()">
            {{ t('common.cancel') }}
          </BaseButton>
        </div>
      </BaseCard>
    </form>

    <!-- Template Variables Reference -->
    <div class="mt-3">
      <div class="card border-0 shadow-sm">
        <div
          class="card-header bg-white d-flex align-items-center justify-content-between py-3"
          style="cursor:pointer;"
          @click="showVariables = !showVariables"
        >
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
              <i class="bi bi-braces text-primary fs-6"></i>
            </span>
            <span class="fw-semibold text-body">{{ t('templates.variablesRef') }}</span>
            <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:.75rem;">{{ TEMPLATE_VARIABLES.length }}</span>
          </div>
          <i class="bi text-secondary" :class="showVariables ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </div>

        <div v-show="showVariables">
          <div class="card-body pb-0 pt-3">
            <p class="text-muted small mb-3">{{ t('templates.variablesDesc') }}</p>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0" style="font-size:.875rem;">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold ps-3" style="width:45%;">{{ t('templates.varVariable') }}</th>
                    <th class="fw-semibold">{{ t('templates.varSource') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="v in TEMPLATE_VARIABLES" :key="v.variable" class="border-bottom">
                    <td class="ps-3 py-2">
                      <button
                        type="button"
                        class="btn btn-sm d-inline-flex align-items-center gap-1 px-2 py-1 font-monospace"
                        :class="copiedVar === v.variable ? 'btn-success' : 'btn-outline-secondary'"
                        :title="t('templates.clickToCopy')"
                        @click.stop="copyVariable(v.variable)"
                      >
                        <i class="bi" :class="copiedVar === v.variable ? 'bi-check-lg' : 'bi-clipboard'"></i>
                        <span>{{ v.variable }}</span>
                      </button>
                      <span v-if="copiedVar === v.variable" class="ms-2 small text-success fw-medium">{{ t('templates.copied') }}</span>
                    </td>
                    <td class="py-2">
                      <span class="text-muted me-2">{{ t('templates.' + v.sourceKey) }}</span>
                      <code class="text-body-secondary" style="font-size:.8rem;">{{ v.source }}</code>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card-footer bg-white border-0 pt-2 pb-3 px-3">
            <div class="d-flex align-items-start gap-2 rounded p-2 bg-info bg-opacity-10">
              <i class="bi bi-info-circle-fill text-info mt-1 flex-shrink-0"></i>
              <p class="mb-0 small text-muted">
                {{ t('templates.previewNote', ['data']) }}
                <code class="text-body-secondary">POST /email-templates/{id}/preview</code>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div v-if="showPreview" class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ t('templates.preview') }}</h5>
            <button type="button" class="btn-close" @click="showPreview = false"></button>
          </div>
          <div class="modal-body" v-html="previewHtml"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showPreview = false">{{ t('common.close') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
