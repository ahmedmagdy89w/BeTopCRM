<script setup lang="ts">
import { useUIStore } from '@/stores/ui'

const ui = useUIStore()

function toggle() {
  ui.setLocale(ui.locale === 'en' ? 'ar' : 'en')
}
</script>

<template>
  <button
    class="btn btn-outline-secondary btn-sm"
    type="button"
    @click="toggle"
  >
    {{ ui.locale === 'en' ? 'عربي' : 'EN' }}
  </button>
</template>
