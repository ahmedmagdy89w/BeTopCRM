<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { OPPORTUNITY_STAGE_COLORS } from '@/utils/constants'
import type { OpportunityStage } from '@/types/enums'

defineProps<{
  stages: OpportunityStage[]
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [stage: OpportunityStage]
}>()

const { t } = useI18n()
const open = ref(false)
const showHelp = ref(false)
const FLOW: OpportunityStage[] = ['prospecting', 'qualification', 'proposal', 'negotiation', 'won']
const root = ref<HTMLElement | null>(null)

const STAGE_ICONS: Record<OpportunityStage, string> = {
  prospecting: 'bi-search',
  qualification: 'bi-patch-check',
  proposal: 'bi-file-earmark-text',
  negotiation: 'bi-chat-left-dots',
  won: 'bi-trophy',
  lost: 'bi-x-octagon',
}

function pick(stage: OpportunityStage) {
  open.value = false
  emit('select', stage)
}

function onDocumentClick(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="root" class="stage-dropdown position-relative">
    <button
      type="button"
      class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"
      :disabled="loading"
      aria-haspopup="listbox"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <i v-else class="bi bi-diagram-3"></i>
      <span>{{ t('opportunities.moveStage') }}</span>
      <i class="bi bi-chevron-down small stage-dropdown__chevron" :class="{ 'is-open': open }"></i>
    </button>

    <Transition name="stage-menu">
      <ul v-if="open" class="stage-dropdown__menu shadow" role="listbox">
        <li class="stage-dropdown__heading pb-1 d-flex align-items-center justify-content-between text-muted text-uppercase small fw-semibold">
          <span>{{ t('opportunities.moveStageTo') }}</span>
          <button
            type="button"
            class="btn btn-sm border-0 p-1 lh-1 text-muted"
            :class="{ 'text-primary': showHelp }"
            :aria-label="t('opportunities.stageHelp.title')"
            :aria-expanded="showHelp"
            @click.stop="showHelp = !showHelp"
          >
            <i class="bi bi-info-circle"></i>
          </button>
        </li>
        <li v-if="showHelp" class="stage-dropdown__help mb-2 p-2 small rounded">
          <div class="fw-semibold mb-1">{{ t('opportunities.stageHelp.title') }}</div>
          <ol class="stage-dropdown__flow mb-1">
            <li v-for="s in FLOW" :key="s">{{ t(`opportunities.stages.${s}`) }}</li>
          </ol>
          <div class="text-muted">{{ t('opportunities.stageHelp.note') }}</div>
        </li>
        <li v-for="s in stages" :key="s" role="option">
          <button type="button" class="stage-dropdown__item w-100 border-0 bg-transparent d-flex align-items-center gap-2" @click="pick(s)">
            <span class="stage-dropdown__icon" :class="`bg-${OPPORTUNITY_STAGE_COLORS[s]}-subtle text-${OPPORTUNITY_STAGE_COLORS[s]}-emphasis`">
              <i class="bi" :class="STAGE_ICONS[s]"></i>
            </span>
            <span class="flex-grow-1 text-start">{{ t(`opportunities.stages.${s}`) }}</span>
            <i class="bi bi-arrow-right-short rtl-flip text-muted"></i>
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>

<style scoped>
.stage-dropdown__menu {
  position: absolute;
  inset-inline-end: 0;
  top: calc(100% + 0.25rem);
  min-width: 15rem;
  z-index: 1050;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.75rem;
}

.stage-dropdown__menu {
  margin: 0;
  padding: 0.5rem 0;
  padding-inline: 0;
  list-style: none;
}

.stage-dropdown__heading {
  padding-inline: 1rem 0.5rem;
}

.stage-dropdown__item {
  padding-block: 0.5rem;
  padding-inline: 1rem;
}

.stage-dropdown__flow {
  margin-inline: 0;
  padding-inline: 1.25rem 0;
}

.stage-dropdown__help {
  margin-inline: 1rem;
  background: var(--bs-info-bg-subtle);
  color: var(--bs-info-text-emphasis);
  text-align: start;
}

.stage-dropdown__chevron {
  transition: transform 0.15s ease;
}

.stage-dropdown__chevron.is-open {
  transform: rotate(180deg);
}

.stage-dropdown__item {
  color: var(--bs-body-color);
  transition: background-color 0.12s ease;
}

.stage-dropdown__item:hover,
.stage-dropdown__item:focus-visible {
  background: var(--bs-tertiary-bg);
  outline: none;
}

.stage-dropdown__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 0.5rem;
}

[dir='rtl'] .rtl-flip {
  transform: scaleX(-1);
}

.stage-menu-enter-active,
.stage-menu-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}

.stage-menu-enter-from,
.stage-menu-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
