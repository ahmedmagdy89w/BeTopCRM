<script setup lang="ts">
import { useUIStore } from '@/stores/ui'
import Sidebar from '@/components/layout/Sidebar.vue'
import Topbar from '@/components/layout/Topbar.vue'
import BreadcrumbBar from '@/components/layout/BreadcrumbBar.vue'
import ErrorBoundary from '@/components/base/ErrorBoundary.vue'

const ui = useUIStore()
</script>

<template>
  <div class="app-layout d-flex">
    <!-- Skip to content link for keyboard users -->
    <a href="#main-content" class="skip-link visually-hidden-focusable position-absolute bg-primary text-white p-2" style="z-index: 2000;">
      Skip to main content
    </a>
    <Sidebar />
    <div
      class="app-layout__main d-flex flex-column flex-grow-1"
      :class="{ 'app-layout__main--collapsed': ui.sidebarCollapsed }"
    >
      <Topbar />
      <main id="main-content" class="app-layout__content flex-grow-1 p-3 p-md-4" role="main">
        <BreadcrumbBar />
        <ErrorBoundary>
          <router-view />
        </ErrorBoundary>
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-layout__main {
  margin-inline-start: var(--crm-sidebar-width);
  min-height: 100vh;
  background-color: var(--crm-body-bg);
  transition: margin-inline-start 0.2s ease;
}

:deep(.breadcrumb) {
  color: #7a8499;
  font-size: 0.82rem;
}

:deep(.card-header__icon) {
  align-items: center;
  background: #f0f0ff;
  border-radius: 0.55rem;
  color: var(--bs-primary);
  display: inline-flex;
  height: 2rem;
  justify-content: center;
  width: 2rem;
}

.app-layout__main--collapsed {
  margin-inline-start: var(--crm-sidebar-collapsed-width);
}
</style>
