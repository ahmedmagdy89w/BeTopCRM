<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProductLabels } from '@/composables/useProductLabels'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { plural: productPlural } = useProductLabels()

interface Crumb {
  label: string
  to?: { name: string; params?: Record<string, string> }
}

const crumbs = computed<Crumb[]>(() => {
  const items: Crumb[] = []
  const name = route.name as string | undefined
  if (!name) return items

  // Dashboard is always first (parent)
  if (name !== 'dashboard') {
    items.push({ label: t('nav.dashboard'), to: { name: 'dashboard' } })
  }

  const segments = name.split('.')
  const noIndexModules = new Set(['reports'])

  if (segments.length >= 1) {
    const module = segments[0] ?? ''
    const moduleLabel = module === 'products' ? productPlural.value : t(`nav.${module}`)
    const hasTranslation = module === 'products' || moduleLabel !== `nav.${module}`

    if (hasTranslation && module !== 'dashboard') {
      if (segments.length === 1) {
        items.push({ label: moduleLabel })
      } else {
        const parentCrumb: Crumb = noIndexModules.has(module)
          ? { label: moduleLabel }
          : { label: moduleLabel, to: { name: `${module}.index` } }
        items.push(parentCrumb)
        const action = segments[segments.length - 1] ?? ''
        if (action === 'create') {
          items.push({ label: t('common.create') })
        } else if (action === 'edit') {
          items.push({ label: t('common.edit') })
        } else if (action === 'show') {
          items.push({ label: t('common.details') })
        } else if (action !== 'index') {
          items.push({ label: t(`nav.${action}`, action) })
        }
      }
    }
  }

  return items
})

// Hide crumb links the user is not allowed to visit (mirrors the router guard).
function canVisit(crumb: Crumb): boolean {
  if (!crumb.to) return true
  try {
    const resolved = router.resolve(crumb.to)
    const meta = resolved.meta
    if (typeof meta.permission === 'string' && meta.permission) {
      return auth.hasPermission(meta.permission)
    }
    const permissions = meta.permissions as string[] | undefined
    if (permissions?.length) {
      return permissions.some(permission => auth.hasPermission(permission))
    }
  } catch {
    return true
  }
  return true
}
</script>

<template>
  <nav v-if="crumbs.length > 0" aria-label="breadcrumb" class="breadcrumb-shell mb-4">
    <span class="breadcrumb-shell__icon" aria-hidden="true">
      <i class="bi bi-compass"></i>
    </span>
    <ol class="breadcrumb mb-0">
      <li
        v-for="(crumb, idx) in crumbs"
        :key="idx"
        class="breadcrumb-item"
        :class="{ active: idx === crumbs.length - 1 }"
        :aria-current="idx === crumbs.length - 1 ? 'page' : undefined"
      >
        <router-link v-if="crumb.to && idx < crumbs.length - 1 && canVisit(crumb)" :to="crumb.to">
          <i v-if="idx === 0" class="bi bi-house-door me-1" aria-hidden="true"></i>{{ crumb.label }}
        </router-link>
        <span v-else class="breadcrumb-current text-truncate d-inline-block" style="max-width: 18rem;">{{ crumb.label }}</span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.breadcrumb-shell {
  align-items: center;
  background: rgba(255, 255, 255, 0.68);
  border: 1px solid #e8ebf2;
  border-radius: 0.75rem;
  display: flex;
  gap: 0.7rem;
  min-height: 2.8rem;
  padding: 0.45rem 0.8rem;
}

.breadcrumb-shell__icon {
  align-items: center;
  background: #f0f0ff;
  border-radius: 0.5rem;
  color: var(--bs-primary);
  display: inline-flex;
  flex: 0 0 auto;
  height: 1.9rem;
  justify-content: center;
  width: 1.9rem;
}

.breadcrumb-shell .breadcrumb {
  align-items: center;
  display: flex;
  flex-wrap: nowrap;
  min-width: 0;
}

.breadcrumb-shell .breadcrumb-item {
  color: #7a8499;
  font-size: 0.8rem;
  white-space: nowrap;
}

.breadcrumb-shell .breadcrumb-item + .breadcrumb-item::before {
  color: #b1b8c8;
  content: '›';
  font-size: 1.1rem;
  line-height: 0.8;
  padding-inline: 0.65rem;
}

.breadcrumb-shell a {
  color: #7a8499;
  text-decoration: none;
}

.breadcrumb-shell a:hover {
  color: var(--bs-primary);
}

.breadcrumb-shell .breadcrumb-item.active {
  color: #202a40;
  font-weight: 700;
}

@media (max-width: 575.98px) {
  .breadcrumb-shell {
    margin-bottom: 1rem !important;
  }

  .breadcrumb-shell .breadcrumb-item:not(:last-child) {
    display: none;
  }
}
</style>

