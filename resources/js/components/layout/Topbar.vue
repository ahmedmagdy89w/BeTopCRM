<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUIStore } from '@/stores/ui'
import LocaleSwitcher from '@/components/layout/LocaleSwitcher.vue'
import NotificationDropdown from '@/components/layout/NotificationDropdown.vue'
import UserMenu from '@/components/layout/UserMenu.vue'

const { t } = useI18n()
const ui = useUIStore()
</script>

<template>
    <header class="topbar d-flex align-items-center justify-content-between border-bottom px-3">
    <!-- Left: toggle + brand -->
    <div class="d-flex align-items-center gap-2">
      <button
        class="btn btn-link text-body p-1"
        type="button"
        :aria-label="t('common.toggleSidebar')"
        @click="ui.toggleSidebar()"
      >
        <i class="bi bi-list fs-4"></i>
      </button>
    </div>

    <!-- Right: actions -->
    <div class="d-flex align-items-center gap-2">
      <NotificationDropdown />
      <LocaleSwitcher />
      <UserMenu />
    </div>
  </header>
</template>

<style scoped>
.topbar {
  height: var(--crm-topbar-height);
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(14px);
  border-bottom-color: #e8ebf2 !important;
  position: sticky;
  top: 0;
  z-index: 1020;
}
</style>
