<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUIStore } from '@/stores/ui'
import { useProductLabels } from '@/composables/useProductLabels'
import { useFeatures } from '@/composables/useFeatures'

const { t } = useI18n()
const { plural: productPlural, productPerformanceTitle } = useProductLabels()
const { showSalesDocuments } = useFeatures()
const route = useRoute()
const auth = useAuthStore()
const ui = useUIStore()

interface NavItem {
  label: string
  icon: string
  to: { name: string }
  permission?: string
  permissions?: string[]
  visible?: boolean
}

interface NavGroup {
  section: string
  sectionIcon?: string
  items: NavItem[]
}

const navGroups = computed<NavGroup[]>(() => [
  {
    section: '',
    items: [
      { label: t('nav.dashboard'), icon: 'bi-speedometer2', to: { name: 'dashboard' } },
    ],
  },
  {
    section: t('nav.crm'),
    sectionIcon: 'bi-diagram-3',
    items: [
      { label: t('nav.customers'), icon: 'bi-people', to: { name: 'customers.index' }, permission: 'view-customers' },
      { label: t('nav.activities'), icon: 'bi-lightning-charge', to: { name: 'activities.index' }, permission: 'view-activities' },
      { label: t('nav.opportunities'), icon: 'bi-kanban', to: { name: 'opportunities.index' }, permission: 'view-opportunities' },
    ],
  },
  {
    section: t('nav.sales'),
    sectionIcon: 'bi-bag',
    items: [
      { label: productPlural.value, icon: 'bi-box-seam', to: { name: 'products.index' }, permission: 'view-products' },
      { label: t('taxes.title'), icon: 'bi-percent', to: { name: 'taxes.index' }, permission: 'view-taxes' },
      { label: t('nav.salesDocuments'), icon: 'bi-file-earmark-pdf', to: { name: 'salesDocuments' }, permissions: ['view-quotations', 'view-invoices'], visible: showSalesDocuments.value },
    ],
  },
  {
    section: t('nav.marketing'),
    sectionIcon: 'bi-megaphone',
    items: [
      { label: t('nav.campaigns'), icon: 'bi-megaphone', to: { name: 'campaigns.index' }, permission: 'view-campaigns' },
      { label: t('nav.emailTemplates'), icon: 'bi-envelope-paper', to: { name: 'templates.index' }, permission: 'create-templates' },
    ],
  },
  {
    section: t('nav.productivity'),
    sectionIcon: 'bi-check2-all',
    items: [
      { label: t('nav.tasks'), icon: 'bi-check2-square', to: { name: 'tasks.index' }, permission: 'view-tasks' },
      { label: t('nav.tags'), icon: 'bi-tags', to: { name: 'tags.index' }, permission: 'create-tags' },
    ],
  },
  {
    section: t('nav.reports'),
    sectionIcon: 'bi-graph-up',
    items: [
      { label: t('reports.userPerformance'), icon: 'bi-bar-chart-line', to: { name: 'reports.userPerformance' }, permission: 'view-reports' },
      { label: t('reports.company'), icon: 'bi-building-check', to: { name: 'reports.company' }, permission: 'view-reports' },
      { label: t('reports.customer'), icon: 'bi-person-lines-fill', to: { name: 'reports.customer' }, permission: 'view-reports' },
      { label: t('reports.leadPipeline'), icon: 'bi-funnel', to: { name: 'reports.leadPipeline' }, permission: 'view-reports' },
      { label: productPerformanceTitle.value, icon: 'bi-box-seam', to: { name: 'reports.productPerformance' }, permission: 'view-reports' },
      { label: t('reports.userProductPerformance'), icon: 'bi-person-badge', to: { name: 'reports.userProductPerformance' }, permission: 'view-reports' },
      { label: t('reports.sales'), icon: 'bi-graph-up-arrow', to: { name: 'reports.sales' }, permission: 'view-reports' },
      { label: t('aiChat.title'), icon: 'bi-stars', to: { name: 'aiAssistant' }, permission: 'create-ai-chats' },
    ],
  },
  {
    section: t('nav.system'),
    sectionIcon: 'bi-sliders',
    items: [
      { label: t('nav.users'), icon: 'bi-person-gear', to: { name: 'users.index' }, permission: 'view-users' },
      { label: t('nav.roles'), icon: 'bi-shield-lock', to: { name: 'roles.index' }, permission: 'view-users' },
      { label: t('nav.auditLogs'), icon: 'bi-journal-text', to: { name: 'auditLogs.index' }, permission: 'view-audit-log' },
      { label: t('nav.webhooks'), icon: 'bi-link-45deg', to: { name: 'webhooks.index' }, permission: 'view-webhooks' },
      { label: t('nav.importExport'), icon: 'bi-arrow-left-right', to: { name: 'importExport' }, permissions: ['import-data', 'export-data'] },
      { label: t('nav.companySettings'), icon: 'bi-building-gear', to: { name: 'company-settings' }, permission: 'view-company-settings' },
      { label: t('nav.customFields'), icon: 'bi-ui-checks', to: { name: 'custom-fields.index' }, permissions: ['create-custom-fields', 'update-custom-fields', 'delete-custom-fields'] },
    ],
  },
])

const visibleGroups = computed(() =>
  navGroups.value
    .map(group => ({
      ...group,
      items: group.items.filter(item =>
        item.visible !== false
        && (!item.permission || auth.hasPermission(item.permission))
        && (!item.permissions || item.permissions.some(permission => auth.hasPermission(permission))),
      ),
    }))
    .filter(group => group.items.length > 0),
)

function isActive(item: NavItem): boolean {
  return route.name === item.to.name || (route.name as string)?.startsWith(item.to.name.replace('.index', '.'))
}

const collapsedSections = ref<string[]>([])

function toggleSection(section: string) {
  if (ui.sidebarCollapsed) return
  const idx = collapsedSections.value.indexOf(section)
  if (idx === -1) {
    collapsedSections.value.push(section)
  } else {
    collapsedSections.value.splice(idx, 1)
  }
}

function isSectionCollapsed(section: string): boolean {
  return !ui.sidebarCollapsed && collapsedSections.value.includes(section)
}
</script>

<template>
  <nav
    class="sidebar d-flex flex-column"
    :class="{ 'sidebar--collapsed': ui.sidebarCollapsed }"
    aria-label="Main navigation"
  >
    <!-- Brand -->
    <div class="sidebar__brand d-flex align-items-center px-3 py-3">
      <span class="sidebar__brand-mark"><i class="bi bi-command"></i></span>
      <span v-if="!ui.sidebarCollapsed" class="sidebar__brand-name">BeTop <span>CRM</span></span>
    </div>

    <!-- Navigation -->
    <div class="sidebar__nav flex-grow-1 py-2" :class="ui.sidebarCollapsed ? 'overflow-visible' : 'overflow-auto'">

      <!-- ── Expanded mode ── -->
      <template v-if="!ui.sidebarCollapsed">
        <template v-for="group in visibleGroups" :key="group.section">
          <div
            v-if="group.section"
            class="sidebar__section px-3 pt-3 pb-1 sidebar__section--clickable"
            @click="toggleSection(group.section)"
          >
            <small class="text-uppercase text-muted fw-semibold d-flex align-items-center justify-content-between" style="font-size: 0.7rem;">
              {{ group.section }}
              <i :class="['bi', isSectionCollapsed(group.section) ? 'bi-chevron-right' : 'bi-chevron-down']" style="font-size: 0.65rem;"></i>
            </small>
          </div>
          <Transition name="section-collapse">
            <ul v-show="!isSectionCollapsed(group.section)" class="nav flex-column">
              <li v-for="item in group.items" :key="item.to.name" class="nav-item">
                <router-link
                  :to="item.to"
                  class="nav-link d-flex align-items-center gap-2 px-3 py-2"
                  :class="{ active: isActive(item) }"
                  :title="item.label"
                >
                  <i :class="['bi', item.icon, 'fs-5', 'bf-icon']"></i>
                  <span>{{ item.label }}</span>
                </router-link>
              </li>
            </ul>
          </Transition>
        </template>
      </template>

      <!-- ── Collapsed mode: one icon per section + hover flyout ── -->
      <template v-else>
        <template v-for="group in visibleGroups" :key="group.section">
          <!-- No-section group (Dashboard): plain icon link -->
          <template v-if="!group.section">
            <ul class="nav flex-column">
              <li v-for="item in group.items" :key="item.to.name" class="nav-item">
                <router-link
                  :to="item.to"
                  class="nav-link d-flex align-items-center justify-content-center px-0 py-2"
                  :class="{ active: isActive(item) }"
                  :title="item.label"
                >
                  <i :class="['bi', item.icon, 'fs-5']"></i>
                </router-link>
              </li>
            </ul>
          </template>

          <!-- Named section: single icon triggers a flyout panel on hover -->
          <div v-else class="sidebar-section-flyout">
            <button
              class="sidebar-section-flyout__trigger"
              :class="{ active: group.items.some(i => isActive(i)) }"
              :title="group.section"
            >
              <i :class="['bi', group.sectionIcon ?? group.items[0]?.icon, 'fs-5']"></i>
            </button>
            <div class="sidebar-flyout-panel" role="menu">
              <p class="sidebar-flyout-panel__title">{{ group.section }}</p>
              <router-link
                v-for="item in group.items"
                :key="item.to.name"
                :to="item.to"
                class="sidebar-flyout-panel__item"
                :class="{ active: isActive(item) }"
                role="menuitem"
              >
                <i :class="['bi', item.icon, 'me-2']"></i>{{ item.label }}
              </router-link>
            </div>
          </div>
        </template>
      </template>

    </div>

  </nav>
</template>

<style scoped>
.sidebar {
  width: var(--crm-sidebar-width);
  height: 100vh;
  position: fixed;
  top: 0;
  inset-inline-start: 0;
  z-index: 1030;
  transition: width 0.2s ease;
  background: var(--crm-sidebar-bg);
  border-inline-end: 1px solid rgba(255, 255, 255, 0.06);
  color: #fff;
}

.sidebar--collapsed {
  width: var(--crm-sidebar-collapsed-width);
}

.sidebar__nav {
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
}

.sidebar__nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar__nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar__nav::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.16);
  border-radius: 999px;
}

.sidebar__nav::-webkit-scrollbar-thumb:hover {
  background-color: rgba(255, 255, 255, 0.3);
}

.sidebar .nav-link {
  color: var(--crm-sidebar-muted);
  border-radius: 0.65rem;
  margin-inline: 0.5rem;
  min-height: 2.55rem;
  white-space: nowrap;
  overflow: hidden;
}

.sidebar .nav-link span {
  white-space: normal;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.bf-icon {
  margin-inline-end: 0.5rem;
}

.sidebar .nav-link:hover {
  background-color: rgba(255, 255, 255, 0.07);
  color: #fff;
}

.sidebar .nav-link.active {
  background: linear-gradient(100deg, #6565e2, #5151c4);
  color: #fff;
  box-shadow: 0 7px 16px rgba(71, 71, 180, 0.28);
}

.sidebar--collapsed .nav-link,
.sidebar--collapsed .sidebar-section-flyout__trigger {
  color: #b8c0d8;
}

.sidebar--collapsed .nav-link:hover,
.sidebar--collapsed .sidebar-section-flyout__trigger:hover,
.sidebar--collapsed .nav-link.active {
  color: #fff;
}

.sidebar__brand {
  min-height: var(--crm-topbar-height);
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.sidebar__brand-mark {
  align-items: center;
  background: #6767e0;
  border-radius: 0.65rem;
  color: #fff;
  display: inline-flex;
  font-size: 1.1rem;
  height: 2.1rem;
  justify-content: center;
  margin-inline-end: 0.7rem;
  width: 2.1rem;
}

.sidebar__brand-name {
  color: #fff;
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.sidebar__brand-name span {
  color: #9595f3;
  font-weight: 500;
}

.sidebar__section small {
  color: #687493 !important;
  letter-spacing: 0.09em;
}

.sidebar__section--clickable {
  cursor: pointer;
  user-select: none;
}

.sidebar__section--clickable:hover small {
  color: #fff !important;
}

.section-collapse-enter-active,
.section-collapse-leave-active {
  transition: opacity 0.18s ease, max-height 0.22s ease;
  overflow: hidden;
  max-height: 400px;
}

.section-collapse-enter-from,
.section-collapse-leave-to {
  opacity: 0;
  max-height: 0;
}

/* ── Collapsed: section flyout ── */
.sidebar-section-flyout {
  position: relative;
}

.sidebar-section-flyout__trigger {
  width: calc(100% - 1rem);
  margin-inline: 0.5rem;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.45rem 0;
  border-radius: 0.375rem;
  color: var(--bs-body-color);
  transition: background-color 0.15s ease, color 0.15s ease;
}

.sidebar-section-flyout:hover .sidebar-section-flyout__trigger,
.sidebar-section-flyout:focus-within .sidebar-section-flyout__trigger {
  background-color: rgba(255, 255, 255, 0.07);
  color: #fff;
}

.sidebar-section-flyout__trigger.active {
  background-color: rgba(var(--bs-primary-rgb), 0.12);
  color: #aaaaff;
}

.sidebar-flyout-panel {
  display: none;
  position: absolute;
  inset-block-start: 0;
  inset-inline-start: calc(100% + 6px);
   background: #202741;
   border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
  min-width: 200px;
  z-index: 1040;
  padding: 0.375rem 0;
}

.sidebar-section-flyout:hover .sidebar-flyout-panel,
.sidebar-section-flyout:focus-within .sidebar-flyout-panel {
  display: block;
}

.sidebar-flyout-panel__title {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
   color: #8e98b5;
  padding: 0.25rem 0.75rem 0.4rem;
  margin: 0;
}

.sidebar-flyout-panel__item {
  display: flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
   color: #c7cde0;
  text-decoration: none;
  border-radius: 0.25rem;
  margin-inline: 0.25rem;
  font-size: 0.9rem;
  transition: background-color 0.12s ease;
  white-space: normal;
  line-height: 1.35;
}

.sidebar-flyout-panel__item:hover {
   background-color: rgba(255, 255, 255, 0.07);
   color: #fff;
}

.sidebar-flyout-panel__item.active {
  background-color: var(--bs-primary);
  color: #fff;
}
</style>
