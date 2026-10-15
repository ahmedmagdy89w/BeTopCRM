<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const store = useNotificationStore()

function handleClick(notification: { id: string; data: { action_url: string }; read_at: string | null }) {
  if (!notification.read_at) {
    store.markRead(notification.id)
  }
  const url = notification.data.action_url
  if (!url) return
  // Don't navigate into routes the user may not visit (avoids the Forbidden page).
  try {
    const resolved = router.resolve(url)
    const meta = resolved.meta
    if (typeof meta.permission === 'string' && meta.permission) {
      if (!auth.hasPermission(meta.permission)) return
    } else {
      const permissions = meta.permissions as string[] | undefined
      if (permissions?.length && !permissions.some(permission => auth.hasPermission(permission))) return
    }
  } catch {
    return
  }
  router.push(url)
}

onMounted(() => {
  store.startPolling()
})
</script>

<template>
  <div class="dropdown">
    <button
      class="btn btn-link text-dark position-relative p-1"
      type="button"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      aria-expanded="false"
      @click="store.fetchNotifications()"
    >
      <i class="bi bi-bell fs-5"></i>
      <span
        v-if="store.hasUnread"
        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
        style="font-size: 0.6rem;"
      >
        {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
      </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow" style="width: 360px; max-height: 420px; overflow-y: auto;">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
        <h6 class="mb-0">{{ t('notifications.title') }}</h6>
        <button
          v-if="store.hasUnread"
          class="btn btn-link btn-sm p-0 text-decoration-none"
          @click="store.markAllRead()"
        >
          {{ t('notifications.markAllRead') }}
        </button>
      </div>

      <div v-if="store.loading" class="text-center py-3">
        <BaseSpinner />
      </div>

      <div v-else-if="store.notifications.length === 0" class="text-center text-muted py-4">
        <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
        {{ t('notifications.noNotifications') }}
      </div>

      <template v-else>
        <button
          v-for="n in store.notifications"
          :key="n.id"
          class="dropdown-item d-flex gap-2 py-2 px-3 border-bottom"
          :class="{ 'bg-light': !n.read_at }"
          @click="handleClick(n)"
        >
          <div class="flex-shrink-0 mt-1">
            <span
              v-if="!n.read_at"
              class="d-inline-block rounded-circle bg-primary"
              style="width: 8px; height: 8px;"
            ></span>
            <span v-else style="width: 8px; display: inline-block;"></span>
          </div>
          <div class="flex-grow-1 text-wrap">
            <div class="fw-semibold small">{{ n.data.title }}</div>
            <div class="text-muted small">{{ n.data.body }}</div>
            <div class="text-muted" style="font-size: 0.7rem;">
              {{ new Date(n.created_at).toLocaleString() }}
            </div>
          </div>
        </button>
      </template>
    </div>
  </div>
</template>
