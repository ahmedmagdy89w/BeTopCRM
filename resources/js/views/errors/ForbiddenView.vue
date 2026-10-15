<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>

<template>
  <div class="text-center py-5">
    <i class="bi bi-shield-lock display-1 text-danger"></i>
    <h2 class="mt-3">{{ t('errors.forbidden') }}</h2>
    <p class="text-muted">{{ t('errors.forbiddenMessage') }}</p>
    <router-link to="/" class="btn btn-primary">{{ t('errors.backHome') }}</router-link>
  </div>
</template>
