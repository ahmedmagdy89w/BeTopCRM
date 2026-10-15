import { ref, onUnmounted } from 'vue'
import { asyncJobService } from '@/services/asyncJobService'
import type { AsyncJob } from '@/types'
import { useToast } from '@/composables/useToast'

export function useAsyncReport() {
  const { success, error: showError } = useToast()

  const asyncJob = ref<AsyncJob | null>(null)
  const polling = ref(false)
  let pollTimer: ReturnType<typeof setInterval> | null = null

  async function startAsync(
    dispatchFn: () => Promise<AsyncJob>,
    onComplete?: (job: AsyncJob) => void,
  ) {
    polling.value = true
    asyncJob.value = null
    try {
      asyncJob.value = await dispatchFn()
      pollTimer = setInterval(async () => {
        if (!asyncJob.value) return
        try {
          asyncJob.value = await asyncJobService.get(asyncJob.value.id)
          if (asyncJob.value.status === 'completed') {
            stopPolling()
            success('reports.asyncCompleted')
            onComplete?.(asyncJob.value)
          } else if (asyncJob.value.status === 'failed') {
            stopPolling()
            showError(asyncJob.value.error ?? 'common.error')
          }
        } catch {
          stopPolling()
          showError('common.error')
        }
      }, 3000)
    } catch {
      polling.value = false
      showError('common.error')
    }
  }

  function stopPolling() {
    polling.value = false
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  onUnmounted(stopPolling)

  return {
    asyncJob,
    polling,
    startAsync,
    stopPolling,
  }
}
