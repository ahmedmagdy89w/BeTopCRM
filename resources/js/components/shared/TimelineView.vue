<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { timelineService } from '@/services/timelineService'
import type { TimelineEvent as TEvent } from '@/types'
import TimelineEvent from '@/components/shared/TimelineEvent.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseButton from '@/components/base/BaseButton.vue'

const { t } = useI18n()

const props = defineProps<{
  entityType: string
  entityId: number
}>()

const events = ref<TEvent[]>([])
const loading = ref(true)
const currentPage = ref(1)
const hasMore = ref(false)

async function fetchEvents(page = 1) {
  loading.value = true
  try {
    const res = await timelineService.forEntity(props.entityType, props.entityId, { page, per_page: 20 })
    if (page === 1) {
      events.value = res.data
    } else {
      events.value.push(...res.data)
    }
    currentPage.value = page
    hasMore.value = (res.meta?.total ?? 0) > events.value.length
  } finally {
    loading.value = false
  }
}

function loadMore() {
  fetchEvents(currentPage.value + 1)
}

onMounted(() => fetchEvents())
</script>

<template>
  <div>
    <div v-if="loading && events.length === 0" class="text-center py-3">
      <BaseSpinner />
    </div>

    <div v-else-if="events.length === 0" class="text-center text-muted py-3">
      {{ t('common.noData') }}
    </div>

    <div v-else class="d-flex flex-column gap-3">
      <TimelineEvent v-for="event in events" :key="event.id" :event="event" />

      <div v-if="hasMore" class="text-center">
        <BaseButton variant="outline-secondary" size="sm" :loading="loading" @click="loadMore">
          Load more
        </BaseButton>
      </div>
    </div>
  </div>
</template>
