<script setup lang="ts">
import { watch, onMounted, onBeforeUnmount } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  scrollable?: boolean
  staticBackdrop?: boolean
  hideFooter?: boolean
  zIndex?: number
}>(), {
  modelValue: false,
  size: 'md',
  scrollable: false,
  staticBackdrop: false,
  hideFooter: false,
  zIndex: 1050,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  close: []
}>()

function close() {
  emit('update:modelValue', false)
  emit('close')
}

function onBackdropClick() {
  if (!props.staticBackdrop) {
    close()
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.modelValue && !props.staticBackdrop) {
    close()
  }
}

watch(() => props.modelValue, (val) => {
  document.body.classList.toggle('modal-open', val)
})

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('modal-open')
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
       <div v-if="modelValue" class="modal-backdrop-custom" :style="{ zIndex }" @click.self="onBackdropClick">
        <div
          :class="[
            'modal-dialog',
            `modal-${size}`,
            { 'modal-dialog-scrollable': scrollable },
          ]"
          role="dialog"
          aria-modal="true"
        >
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{ title }}</h5>
              <button type="button" class="btn-close" @click="close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <slot />
            </div>
            <div v-if="!hideFooter" class="modal-footer">
              <slot name="footer">
                <button type="button" class="btn btn-secondary" @click="close">Close</button>
              </slot>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
.modal-backdrop-custom {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1rem;
  /* Bootstrap defines --bs-modal-bg on .modal; supply it here since we skip that wrapper */
  --bs-modal-bg: #fff;
  --bs-modal-header-border-color: #dee2e6;
  --bs-modal-footer-border-color: #dee2e6;
  --bs-modal-border-color: rgba(0, 0, 0, 0.175);
  --bs-modal-border-radius: 0.5rem;
  --bs-modal-inner-border-radius: calc(0.5rem - 1px);
  --bs-modal-header-padding: 1rem 1rem;
  --bs-modal-padding: 1rem;
  --bs-modal-footer-gap: 0.5rem;
}

.modal-footer .btn {
  padding: 0.5rem 1.25rem;
}

.modal-dialog {
  width: 100%;
  margin: 1.75rem auto;
}

.modal-sm { max-width: 300px; }
.modal-md { max-width: 500px; }
.modal-lg { max-width: 800px; }
.modal-xl { max-width: 1140px; }

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
