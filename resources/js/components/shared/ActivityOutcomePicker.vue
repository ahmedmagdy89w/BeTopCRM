<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityOutcome } from '@/types/enums'

const props = withDefaults(
  defineProps<{
    modelValue?: ActivityOutcome | ''
    disabled?: boolean
    loading?: boolean
  }>(),
  { modelValue: '', disabled: false, loading: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: ActivityOutcome | '']
  complete: []
}>()

const { t } = useI18n()

interface OutcomeOption {
  value: ActivityOutcome
  dot: string
  icon: string
  btn: string
}

const OUTCOMES: OutcomeOption[] = [
  { value: 'positive', dot: 'bg-success', icon: 'bi-emoji-smile', btn: 'success' },
  { value: 'neutral', dot: 'bg-secondary', icon: 'bi-dash-circle', btn: 'secondary' },
  { value: 'negative', dot: 'bg-danger', icon: 'bi-emoji-frown', btn: 'danger' },
  { value: 'escalated', dot: 'bg-warning', icon: 'bi-flag', btn: 'warning' },
]

const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const toggleRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const menuStyle = ref<Record<string, string>>({})

const selected = computed(() => OUTCOMES.find((o) => o.value === props.modelValue) ?? null)

const actionBtn = computed(() => `btn-outline-${selected.value?.btn ?? 'success'}`)

const mainLabel = computed(() =>
  selected.value ? t(`customers.activityOutcomes.${selected.value.value}`) : t('customers.activitySelectOutcome'),
)

const canComplete = computed(() => !!props.modelValue && !props.disabled && !props.loading)

async function toggle() {
  if (props.disabled || props.loading) return
  open.value = !open.value
  if (open.value) {
    await nextTick()
    updateMenuPosition()
  }
}

function pick(value: ActivityOutcome | '') {
  emit('update:modelValue', value)
  open.value = false
}

function onComplete() {
  if (!canComplete.value) return
  emit('complete')
}

function onClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (rootRef.value?.contains(target) || menuRef.value?.contains(target)) return
  open.value = false
}

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') open.value = false
}

function updateMenuPosition() {
  if (!open.value || !toggleRef.value) return
  const bounds = toggleRef.value.getBoundingClientRect()
  const menuHeight = menuRef.value?.offsetHeight || 220
  const shouldOpenAbove = bounds.bottom + menuHeight + 4 > window.innerHeight && bounds.top > menuHeight
  const style: Record<string, string> = {
    width: `${Math.max(bounds.width, 192)}px`,
    top: `${shouldOpenAbove ? Math.max(4, bounds.top - menuHeight - 4) : bounds.bottom + 4}px`,
  }
  if (document.documentElement.dir === 'rtl') {
    style.right = `${window.innerWidth - bounds.right}px`
  } else {
    style.left = `${bounds.left}px`
  }
  menuStyle.value = style
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
  document.addEventListener('keydown', onEscape)
  window.addEventListener('scroll', updateMenuPosition, true)
  window.addEventListener('resize', updateMenuPosition)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  document.removeEventListener('keydown', onEscape)
  window.removeEventListener('scroll', updateMenuPosition, true)
  window.removeEventListener('resize', updateMenuPosition)
})
</script>

<template>
  <div ref="rootRef" class="btn-group outcome-picker">
    <button
      type="button"
      class="btn btn-sm outcome-picker__action"
      :class="actionBtn"
      :disabled="!canComplete"
      :title="mainLabel"
      @click="onComplete"
    >
      <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
      <i v-else class="bi bi-check-lg me-1"></i>{{ mainLabel }}
    </button>
    <button
      ref="toggleRef"
      type="button"
      class="btn btn-sm dropdown-toggle dropdown-toggle-split"
      :class="actionBtn"
      :disabled="disabled || loading"
      :aria-expanded="open"
      :aria-label="t('customers.activityOutcome')"
      @click="toggle"
    >
      <span class="visually-hidden">{{ t('customers.activityOutcome') }}</span>
    </button>
    <Teleport to="body">
      <ul
        v-if="open"
        ref="menuRef"
        class="dropdown-menu show outcome-picker__menu"
        :style="menuStyle"
      >
        <li v-for="o in OUTCOMES" :key="o.value">
          <button
            type="button"
            class="dropdown-item d-flex align-items-center gap-2"
            :class="{ active: modelValue === o.value }"
            @click="pick(o.value)"
          >
            <span class="outcome-dot" :class="o.dot"></span>
            <i :class="['bi', o.icon]"></i>
            {{ t(`customers.activityOutcomes.${o.value}`) }}
            <i v-if="modelValue === o.value" class="bi bi-check-lg ms-auto"></i>
          </button>
        </li>
        <li v-if="modelValue"><hr class="dropdown-divider" /></li>
        <li v-if="modelValue">
          <button type="button" class="dropdown-item text-muted" @click="pick('')">
            {{ t('customers.activityNoOutcome') }}
          </button>
        </li>
      </ul>
    </Teleport>
  </div>
</template>

<style scoped>
.outcome-dot {
  inline-size: 0.625rem;
  block-size: 0.625rem;
  border-radius: 9999px;
  display: inline-block;
  flex-shrink: 0;
}

.outcome-picker__action {
  max-inline-size: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.outcome-picker__menu {
  position: fixed;
  z-index: 2000;
  min-inline-size: 12rem;
  margin: 0;
}
</style>
