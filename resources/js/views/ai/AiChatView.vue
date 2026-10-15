<script setup lang="ts">
import { ref, reactive, nextTick, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { aiChatService, type AiChatAskParams, type AiChatAnswer } from '@/services/aiChatService'
import { asyncJobService } from '@/services/asyncJobService'
import { useToast } from '@/composables/useToast'
import { useProductLabels } from '@/composables/useProductLabels'
import { usePermission } from '@/composables/usePermission'
import { useCompanySettingsStore } from '@/stores/companySettings'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseAlert from '@/components/base/BaseAlert.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import CustomerPicker from '@/components/shared/CustomerPicker.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const { t, te, locale } = useI18n()
const toast = useToast()
const { can } = usePermission()
const { plural: productPlural } = useProductLabels()
const companySettings = useCompanySettingsStore()

const aiUnconfigured = computed(() => {
  const ai = companySettings.settings?.ai
  return companySettings.loaded && !ai?.provider && !ai?.model && !ai?.api_key_set
})

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  sources?: string[]
  at: Date
}

const messages = ref<ChatMessage[]>([])
const suggested = ref<string[]>([])
const loadingSuggestions = ref(false)
const asking = ref(false)
const errors = ref<Record<string, string[]>>({})
const copiedIdx = ref<number | null>(null)
const messagesEnd = ref<HTMLElement | null>(null)

const form = reactive<AiChatAskParams>({
  question: '',
  scope: 'all',
  user_id: null,
  customer_id: null,
  customer_type: '',
  date_from: '',
  date_to: '',
  language: locale.value === 'ar' ? 'ar' : 'en',
})

const SUGGEST_ICONS = [
  'bi-graph-up-arrow',
  'bi-trophy',
  'bi-heart-pulse',
  'bi-funnel',
  'bi-box-seam',
  'bi-lightbulb',
]

let pollTimer: ReturnType<typeof setInterval> | null = null
let copiedTimer: ReturnType<typeof setTimeout> | null = null

function stopPolling() {
  asking.value = false
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function scrollToBottom() {
  nextTick(() => {
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  })
}

function formatTime(date: Date): string {
  return date.toLocaleTimeString(locale.value === 'ar' ? 'ar' : 'en', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function sourceLabel(source: string): string {
  if (source === 'products') return productPlural.value
  const key = `aiChat.sourceLabels.${source}`
  return te(key) ? t(key) : source
}

function formatSources(sources?: string[]): string {
  if (!sources?.length) return ''
  return sources.map(sourceLabel).join(', ')
}

async function fetchSuggested() {
  loadingSuggestions.value = true
  try {
    suggested.value = await aiChatService.suggestedQuestions()
  } catch {
    suggested.value = []
  } finally {
    loadingSuggestions.value = false
  }
}

function useSuggested(question: string) {
  form.question = question
  ask()
}

function clearChat() {
  stopPolling()
  messages.value = []
}

async function copyAnswer(idx: number, content: string) {
  try {
    await navigator.clipboard.writeText(content)
    copiedIdx.value = idx
    if (copiedTimer) clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copiedIdx.value = null }, 2000)
  } catch {
    toast.error(t('common.error'))
  }
}

// --- Voice input (Web Speech API) ---
interface SpeechAlternative {
  transcript: string
}

interface SpeechResult extends ArrayLike<SpeechAlternative> {
  isFinal: boolean
}

interface SpeechResultList extends ArrayLike<SpeechResult> {}

interface SpeechRecognitionResultEvent extends Event {
  results: SpeechResultList
}

interface SpeechRecognitionError extends Event {
  error: string
}

interface SpeechRecognitionInstance extends EventTarget {
  lang: string
  interimResults: boolean
  continuous: boolean
  onresult: ((event: SpeechRecognitionResultEvent) => void) | null
  onerror: ((event: SpeechRecognitionError) => void) | null
  onend: (() => void) | null
  start(): void
  stop(): void
}

type SpeechRecognitionCtor = new () => SpeechRecognitionInstance

const listening = ref(false)
let recognition: SpeechRecognitionInstance | null = null

function recognitionCtor(): SpeechRecognitionCtor | null {
  const w = window as unknown as {
    SpeechRecognition?: SpeechRecognitionCtor
    webkitSpeechRecognition?: SpeechRecognitionCtor
  }
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null
}

const voiceSupported = computed(() => recognitionCtor() !== null)

function stopVoice() {
  listening.value = false
  if (recognition) {
    recognition.onresult = null
    recognition.onerror = null
    recognition.onend = null
    try {
      recognition.stop()
    } catch {
      /* already stopped */
    }
    recognition = null
  }
}

function toggleVoice() {
  if (listening.value) {
    stopVoice()
    return
  }
  const Ctor = recognitionCtor()
  if (!Ctor) {
    toast.error(t('aiChat.voiceUnsupported'))
    return
  }
  const instance = new Ctor()
  instance.lang = form.language === 'ar' ? 'ar-SA' : 'en-US'
  instance.interimResults = false
  instance.continuous = true
  instance.onresult = (event) => {
    const transcripts: string[] = []
    for (let i = 0; i < event.results.length; i++) {
      const result = event.results[i]
      if (result?.isFinal) {
        transcripts.push(result[0]?.transcript ?? '')
      }
    }
    const text = transcripts.join(' ').trim()
    if (text) {
      form.question = form.question ? `${form.question} ${text}` : text
    }
  }
  instance.onerror = (event) => {
    // Fatal errors stop recording; transient ones (no-speech, network)
    // fall through to onend, which resumes listening.
    if (event.error === 'not-allowed' || event.error === 'service-not-allowed' || event.error === 'audio-capture') {
      stopVoice()
      toast.error(t('aiChat.voiceError'))
    }
  }
  instance.onend = () => {
    if (!listening.value) {
      if (recognition === instance) {
        recognition = null
      }
      return
    }
    // Keep recording until the user taps the mic again.
    try {
      instance.start()
    } catch {
      stopVoice()
    }
  }
  recognition = instance
  try {
    instance.start()
    listening.value = true
  } catch {
    stopVoice()
    toast.error(t('aiChat.voiceError'))
  }
}

async function ask() {
  errors.value = {}
  stopVoice()
  if (!form.question.trim()) {
    errors.value = { question: [t('common.required')] }
    return
  }
  const userText = form.question.trim()
  messages.value.push({ role: 'user', content: userText, at: new Date() })
  form.question = ''
  asking.value = true
  scrollToBottom()
  try {
    const job = await aiChatService.askAsync({ ...form, question: userText })
    pollTimer = setInterval(async () => {
      try {
        const polled = await asyncJobService.get(job.id)
        if (polled.status === 'completed') {
          stopPolling()
          const result = polled.result as AiChatAnswer
          messages.value.push({ role: 'assistant', content: result.answer, sources: result.sources, at: new Date() })
          scrollToBottom()
        } else if (polled.status === 'failed') {
          stopPolling()
          toast.error(polled.error ?? t('common.error'))
          messages.value.push({ role: 'assistant', content: t('aiChat.failed'), at: new Date() })
          scrollToBottom()
        }
      } catch {
        stopPolling()
        toast.error(t('common.error'))
      }
    }, 3000)
  } catch (err: unknown) {
    asking.value = false
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.error'))
    }
  }
}

onMounted(fetchSuggested)
onMounted(() => {
  if (can('view-company-settings')) {
    void companySettings.fetchSettings()
  }
})
onBeforeUnmount(() => {
  stopPolling()
  stopVoice()
  if (copiedTimer) clearTimeout(copiedTimer)
})
</script>

<template>
  <div class="container-fluid py-3 ai-chat-page">
    <!-- Hero header -->
    <div class="ai-hero d-flex align-items-center gap-3 p-4 mb-3">
      <span class="ai-hero__icon" aria-hidden="true">
        <i class="bi bi-stars"></i>
      </span>
      <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h2 class="ai-hero__title mb-0">{{ t('aiChat.title') }}</h2>
          <BaseBadge variant="info">AI</BaseBadge>
        </div>
        <p class="ai-hero__subtitle mb-0">{{ t('aiChat.subtitle') }}</p>
      </div>
      <BaseButton
        v-if="messages.length"
        variant="outline-secondary"
        size="sm"
        icon="bi-trash"
        class="ai-hero__clear"
        @click="clearChat"
      >
        {{ t('aiChat.clear') }}
      </BaseButton>
    </div>

    <BaseAlert v-if="aiUnconfigured" variant="warning" class="mb-3">
      <i class="bi bi-exclamation-triangle me-2"></i>{{ t('aiChat.notConfigured') }}
      <router-link
        v-if="can('update-company-settings')"
        :to="{ name: 'company-settings' }"
        class="alert-link ms-1"
      >
        {{ t('aiChat.configureAi') }}
      </router-link>
    </BaseAlert>

    <div class="row g-3">
      <!-- Chat thread -->
      <div class="col-xl-8">
        <BaseCard :title="t('aiChat.threadTitle')" icon="bi-chat-dots" no-padding>
          <div class="ai-thread" aria-live="polite">
            <!-- Empty state -->
            <div v-if="!messages.length && !asking" class="ai-empty">
              <span class="ai-empty__icon" aria-hidden="true">
                <i class="bi bi-stars"></i>
              </span>
              <p class="ai-empty__title">{{ t('aiChat.emptyState') }}</p>
              <p class="ai-empty__hint">{{ t('aiChat.emptyHint') }}</p>
            </div>

            <!-- Messages -->
            <div
              v-for="(msg, idx) in messages"
              :key="idx"
              class="ai-msg"
              :class="msg.role === 'user' ? 'ai-msg--user' : 'ai-msg--assistant'"
            >
              <span class="ai-msg__avatar" aria-hidden="true">
                <i :class="msg.role === 'user' ? 'bi bi-person' : 'bi bi-stars'"></i>
              </span>
              <div class="ai-msg__bubble">
                <div class="ai-msg__meta">
                  <span class="ai-msg__author">
                    {{ msg.role === 'user' ? t('aiChat.you') : t('aiChat.assistant') }}
                  </span>
                  <span class="ai-msg__time">{{ formatTime(msg.at) }}</span>
                </div>
                <div class="ai-msg__content">{{ msg.content }}</div>
                <div v-if="msg.sources?.length" class="ai-msg__sources">
                  <i class="bi bi-link-45deg" aria-hidden="true"></i>
                  {{ t('aiChat.sources') }}: {{ formatSources(msg.sources) }}
                </div>
                <button
                  v-if="msg.role === 'assistant'"
                  type="button"
                  class="btn btn-link btn-sm ai-msg__copy"
                  @click="copyAnswer(idx, msg.content)"
                >
                  <i :class="copiedIdx === idx ? 'bi bi-check-lg' : 'bi bi-clipboard'" aria-hidden="true"></i>
                  {{ copiedIdx === idx ? t('aiChat.copied') : t('aiChat.copy') }}
                </button>
              </div>
            </div>

            <!-- Typing indicator -->
            <div v-if="asking" class="ai-msg ai-msg--assistant">
              <span class="ai-msg__avatar" aria-hidden="true">
                <i class="bi bi-stars"></i>
              </span>
              <div class="ai-msg__bubble ai-typing">
                <span class="ai-typing__text">{{ t('aiChat.thinking') }}</span>
                <span class="ai-typing__dots" aria-hidden="true">
                  <span></span><span></span><span></span>
                </span>
              </div>
            </div>
            <div ref="messagesEnd"></div>
          </div>

          <!-- Composer -->
          <div class="ai-composer">
            <BaseAlert v-if="errors.question" variant="danger" class="mb-3">
              {{ errors.question[0] }}
            </BaseAlert>
            <form @submit.prevent="ask">
              <div class="ai-composer__row">
                <button
                  v-if="voiceSupported"
                  type="button"
                  class="ai-mic"
                  :class="{ 'ai-mic--live': listening }"
                  :title="t('aiChat.voiceInput')"
                  :aria-label="t('aiChat.voiceInput')"
                  :aria-pressed="listening"
                  :disabled="asking"
                  @click="toggleVoice"
                >
                  <i :class="listening ? 'bi bi-mic-fill' : 'bi bi-mic'" aria-hidden="true"></i>
                </button>
                <input
                  v-model="form.question"
                  type="text"
                  class="form-control ai-composer__input"
                  :placeholder="listening ? t('aiChat.listening') : t('aiChat.placeholder')"
                  :disabled="asking"
                />
                <BaseButton
                  type="submit"
                  icon="bi-send"
                  :loading="asking"
                  :disabled="asking || listening"
                  class="ai-composer__send"
                >
                  {{ t('aiChat.send') }}
                </BaseButton>
              </div>
            </form>
          </div>
        </BaseCard>
      </div>

      <!-- Side panel -->
      <div class="col-xl-4">
        <!-- Suggested questions -->
        <BaseCard :title="t('aiChat.suggestedTitle')" icon="bi-lightbulb" class="mb-3">
          <div v-if="loadingSuggestions" class="d-flex align-items-center gap-2 text-muted">
            <BaseSpinner />
            <span>{{ t('common.loading') }}</span>
          </div>
          <div v-else-if="suggested.length" class="d-flex flex-column gap-2">
            <button
              v-for="(q, idx) in suggested"
              :key="q"
              type="button"
              class="ai-suggest"
              :disabled="asking"
              @click="useSuggested(q)"
            >
              <i :class="['bi', SUGGEST_ICONS[idx % SUGGEST_ICONS.length]]" aria-hidden="true"></i>
              <span>{{ q }}</span>
              <i class="bi bi-chevron-right ai-suggest__go" aria-hidden="true"></i>
            </button>
          </div>
          <p v-else class="text-muted small mb-0">{{ t('common.noData') }}</p>
        </BaseCard>

        <!-- Filters -->
        <BaseCard :title="t('aiChat.filters')" icon="bi-sliders">
          <div class="d-flex flex-column gap-3">
            <div>
              <label class="form-label fw-semibold">{{ t('aiChat.scope') }}</label>
              <select v-model="form.scope" class="form-select">
                <option value="all">{{ t('aiChat.scopes.all') }}</option>
                <option value="reports">{{ t('aiChat.scopes.reports') }}</option>
                <option value="pipeline">{{ t('aiChat.scopes.pipeline') }}</option>
                <option value="customers">{{ t('aiChat.scopes.customers') }}</option>
                <option value="products">{{ t('aiChat.scopes.products') }}</option>
              </select>
            </div>
            <div>
              <label class="form-label fw-semibold">{{ t('reports.user') }}</label>
              <UserPicker v-model="form.user_id" :teleport-dropdown="true" />
            </div>
            <div>
              <label class="form-label fw-semibold">{{ t('reports.customer_name') }}</label>
              <CustomerPicker v-model="form.customer_id" :show-quick-add="false" :teleport-dropdown="true" />
            </div>
            <div>
              <label class="form-label fw-semibold">{{ t('customers.type') }}</label>
              <select v-model="form.customer_type" class="form-select">
                <option value="">{{ t('common.all') }}</option>
                <option value="individual">{{ t('customers.individual') }}</option>
                <option value="company">{{ t('customers.company') }}</option>
              </select>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label fw-semibold">{{ t('reports.dateFrom') }}</label>
                <input v-model="form.date_from" type="date" class="form-control" />
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">{{ t('reports.dateTo') }}</label>
                <input v-model="form.date_to" type="date" class="form-control" />
              </div>
            </div>
            <div>
              <label class="form-label fw-semibold">{{ t('templates.ai.language') }}</label>
              <select v-model="form.language" class="form-select">
                <option value="en">{{ t('templates.ai.languages.en') }}</option>
                <option value="ar">{{ t('templates.ai.languages.ar') }}</option>
              </select>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ai-hero {
  background: linear-gradient(120deg, #3b3bb3 0%, #5b5bd6 55%, #7b6ff0 100%);
  border-radius: 1rem;
  color: #fff;
  box-shadow: 0 10px 28px rgba(91, 91, 214, 0.3);
  overflow: hidden;
  position: relative;
}

.ai-hero::after {
  content: '';
  position: absolute;
  inset-inline-end: -60px;
  inset-block-start: -60px;
  inline-size: 200px;
  block-size: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, transparent 70%);
  pointer-events: none;
}

.ai-hero__icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 1rem;
  color: #fff;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.6rem;
  height: 3.4rem;
  justify-content: center;
  width: 3.4rem;
}

.ai-hero__title {
  color: #fff;
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.ai-hero__subtitle {
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.9rem;
}

.ai-hero__clear {
  --bs-btn-color: #fff;
  --bs-btn-border-color: rgba(255, 255, 255, 0.5);
  --bs-btn-hover-bg: rgba(255, 255, 255, 0.15);
  --bs-btn-hover-border-color: #fff;
  --bs-btn-hover-color: #fff;
  flex: 0 0 auto;
}

.ai-thread {
  block-size: 26rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  overflow-y: auto;
  padding: 1.25rem;
  background:
    radial-gradient(circle at 15% 0%, rgba(var(--bs-primary-rgb), 0.05), transparent 45%),
    var(--bs-body-bg);
}

.ai-empty {
  align-items: center;
  display: flex;
  flex-direction: column;
  justify-content: center;
  margin: auto;
  max-inline-size: 26rem;
  text-align: center;
}

.ai-empty__icon {
  align-items: center;
  background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.14), rgba(var(--bs-primary-rgb), 0.04));
  border-radius: 50%;
  color: var(--bs-primary);
  display: inline-flex;
  font-size: 2rem;
  height: 4.5rem;
  justify-content: center;
  margin-block-end: 1rem;
  width: 4.5rem;
}

.ai-empty__title {
  color: var(--bs-body-color);
  font-weight: 700;
  margin-block-end: 0.25rem;
}

.ai-empty__hint {
  color: var(--bs-secondary);
  font-size: 0.85rem;
  margin-block-end: 0;
}

.ai-msg {
  display: flex;
  gap: 0.65rem;
  max-inline-size: 88%;
}

.ai-msg--user {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.ai-msg--assistant {
  align-self: flex-start;
}

.ai-msg__avatar {
  align-items: center;
  border-radius: 50%;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1rem;
  height: 2.2rem;
  justify-content: center;
  width: 2.2rem;
}

.ai-msg--user .ai-msg__avatar {
  background: linear-gradient(135deg, #5b5bd6, #7b6ff0);
  color: #fff;
}

.ai-msg--assistant .ai-msg__avatar {
  background: rgba(var(--bs-primary-rgb), 0.12);
  border: 1px solid rgba(var(--bs-primary-rgb), 0.25);
  color: var(--bs-primary);
}

.ai-msg__bubble {
  background: #fff;
  border: 1px solid var(--bs-border-color);
  border-radius: 1rem;
  box-shadow: var(--bs-box-shadow-sm);
  min-inline-size: 0;
  padding: 0.75rem 1rem;
}

.ai-msg--user .ai-msg__bubble {
  background: linear-gradient(135deg, #5b5bd6, #6d6de0);
  border: none;
  border-start-end-radius: 0.25rem;
  color: #fff;
}

.ai-msg--assistant .ai-msg__bubble {
  border-start-start-radius: 0.25rem;
}

.ai-msg__meta {
  align-items: baseline;
  display: flex;
  gap: 0.5rem;
  margin-block-end: 0.25rem;
}

.ai-msg__author {
  font-size: 0.75rem;
  font-weight: 700;
}

.ai-msg--user .ai-msg__author {
  color: rgba(255, 255, 255, 0.9);
}

.ai-msg--assistant .ai-msg__author {
  color: var(--bs-primary);
}

.ai-msg__time {
  font-size: 0.7rem;
  opacity: 0.65;
}

.ai-msg__content {
  font-size: 0.92rem;
  line-height: 1.65;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.ai-msg__sources {
  border-block-start: 1px dashed var(--bs-border-color);
  color: var(--bs-secondary);
  font-size: 0.72rem;
  margin-block-start: 0.5rem;
  padding-block-start: 0.4rem;
}

.ai-msg--user .ai-msg__sources {
  border-color: rgba(255, 255, 255, 0.35);
  color: rgba(255, 255, 255, 0.85);
}

.ai-msg__copy {
  color: var(--bs-secondary);
  font-size: 0.72rem;
  padding: 0.25rem 0;
  text-decoration: none;
}

.ai-msg__copy:hover {
  color: var(--bs-primary);
}

.ai-typing {
  align-items: center;
  display: flex;
  gap: 0.6rem;
}

.ai-typing__text {
  color: var(--bs-secondary);
  font-size: 0.85rem;
}

.ai-typing__dots {
  display: inline-flex;
  gap: 0.25rem;
}

.ai-typing__dots span {
  animation: ai-bounce 1.2s infinite ease-in-out;
  background: var(--bs-primary);
  border-radius: 50%;
  display: inline-block;
  height: 0.45rem;
  opacity: 0.4;
  width: 0.45rem;
}

.ai-typing__dots span:nth-child(2) {
  animation-delay: 0.15s;
}

.ai-typing__dots span:nth-child(3) {
  animation-delay: 0.3s;
}

@keyframes ai-bounce {
  0%, 60%, 100% {
    opacity: 0.4;
    transform: translateY(0);
  }
  30% {
    opacity: 1;
    transform: translateY(-4px);
  }
}

.ai-composer {
  border-block-start: 1px solid var(--bs-border-color);
  padding: 1rem 1.25rem;
}

.ai-composer__row {
  display: flex;
  gap: 0.6rem;
}

.ai-composer__input {
  border-radius: 999px;
  padding-inline: 1.25rem;
}

.ai-composer__send {
  border-radius: 999px;
  flex: 0 0 auto;
  padding-inline: 1.4rem;
}

.ai-mic {
  align-items: center;
  background: #fff;
  border: 1px solid var(--bs-border-color);
  border-radius: 50%;
  color: var(--bs-secondary);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.05rem;
  height: 2.65rem;
  justify-content: center;
  transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
  width: 2.65rem;
}

.ai-mic:hover:not(:disabled) {
  border-color: rgba(var(--bs-primary-rgb), 0.55);
  color: var(--bs-primary);
}

.ai-mic:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ai-mic--live {
  animation: ai-mic-pulse 1.4s infinite;
  background: var(--bs-danger);
  border-color: var(--bs-danger);
  color: #fff;
}

.ai-mic--live:hover:not(:disabled) {
  color: #fff;
}

@keyframes ai-mic-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(var(--bs-danger-rgb), 0.45);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(var(--bs-danger-rgb), 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(var(--bs-danger-rgb), 0);
  }
}

.ai-suggest {
  align-items: center;
  background: #fff;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.75rem;
  color: var(--bs-body-color);
  display: flex;
  font-size: 0.85rem;
  gap: 0.65rem;
  padding: 0.7rem 0.85rem;
  text-align: start;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
  width: 100%;
}

.ai-suggest > .bi:first-child {
  color: var(--bs-primary);
  flex: 0 0 auto;
  font-size: 1rem;
}

.ai-suggest > span {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.ai-suggest__go {
  color: var(--bs-secondary);
  flex: 0 0 auto;
  font-size: 0.75rem;
  opacity: 0;
  transition: opacity 0.15s ease;
}

[dir="rtl"] .ai-suggest__go::before {
  transform: scaleX(-1);
}

.ai-suggest:hover:not(:disabled) {
  border-color: rgba(var(--bs-primary-rgb), 0.5);
  box-shadow: 0 4px 14px rgba(var(--bs-primary-rgb), 0.12);
  transform: translateY(-1px);
}

.ai-suggest:hover:not(:disabled) .ai-suggest__go {
  opacity: 1;
}

.ai-suggest:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
</style>
