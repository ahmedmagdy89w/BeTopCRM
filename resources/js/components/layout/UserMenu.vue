<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="dropdown">
    <button
      class="btn btn-link text-body text-decoration-none d-flex align-items-center gap-2 dropdown-toggle"
      type="button"
      data-bs-toggle="dropdown"
      aria-expanded="false"
    >
      <i class="bi bi-person-circle fs-5"></i>
      <span class="d-none d-md-inline">{{ auth.user?.name }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li>
        <span class="dropdown-item-text small text-muted">
          {{ auth.user?.email }}
        </span>
      </li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <router-link :to="{ name: 'settings' }" class="dropdown-item">
          <i class="bi bi-gear bf-icon"></i>{{ t('nav.settings') }}
        </router-link>
      </li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <button class="dropdown-item text-danger" type="button" @click="handleLogout">
          <i class="bi bi-box-arrow-right bf-icon"></i>{{ t('auth.logout') }}
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
/* Logical icon spacing that respects LTR/RTL */
.bf-icon {
  margin-inline-end: 0.5rem;
}
</style>
