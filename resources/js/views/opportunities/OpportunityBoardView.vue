<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import { opportunityService } from '@/services/opportunityService'
import { userService } from '@/services/userService'
import { OPEN_OPPORTUNITY_STAGES, CLOSED_OPPORTUNITY_STAGES, OPPORTUNITY_STAGE_COLORS } from '@/utils/constants'
import { daysAgoInput } from '@/utils/formatters'
import type { Opportunity } from '@/types'
import type { OpportunityStage } from '@/types/enums'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import UserAvatar from '@/components/shared/UserAvatar.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const toast = useToast()

const STAGE_TRANSITIONS: Record<OpportunityStage, OpportunityStage[]> = {
  prospecting: ['qualification', 'lost'],
  qualification: ['prospecting', 'proposal', 'lost'],
  proposal: ['qualification', 'negotiation', 'lost'],
  negotiation: ['proposal', 'won', 'lost'],
  won: [],
  lost: ['prospecting'],
}

const loading = ref(true)
const cards = ref<Opportunity[]>([])
const users = ref<{ id: number; name: string }[]>([])
const assigneeFilter = ref<string>('')
const searchQuery = ref<string>('')
const showClosed = ref(true)
const dateFrom = ref<string>(daysAgoInput(29))
const dateTo = ref<string>('')
const movingId = ref<number | null>(null)

const visibleStages = computed<OpportunityStage[]>(() =>
  showClosed.value ? [...OPEN_OPPORTUNITY_STAGES, ...CLOSED_OPPORTUNITY_STAGES] : [...OPEN_OPPORTUNITY_STAGES],
)

const columns = computed<Record<OpportunityStage, Opportunity[]>>(() => {
  const map = {} as Record<OpportunityStage, Opportunity[]>
  for (const s of visibleStages.value) map[s] = []
  for (const o of cards.value) {
    const bucket = map[o.stage]
    if (bucket) bucket.push(o)
  }
  return map
})

const columnTotals = computed<Record<string, number>>(() => {
  const totals: Record<string, number> = {}
  for (const s of visibleStages.value) {
    totals[s] = columns.value[s]!.reduce((sum, o) => sum + Number(o.amount ?? 0), 0)
  }
  return totals
})

async function fetchBoard() {
  loading.value = true
  try {
    const res = await opportunityService.listGlobal({
      per_page: 100,
      status: showClosed.value ? undefined : 'open',
      assigned_to: assigneeFilter.value ? Number(assigneeFilter.value) : undefined,
      search: searchQuery.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
    cards.value = res.data ?? []
  } finally {
    loading.value = false
  }
}

function canMove(from: OpportunityStage, to: OpportunityStage): boolean {
  return (STAGE_TRANSITIONS[from] ?? []).includes(to)
}

async function onDrop(toStage: OpportunityStage, evt: { added?: { element: Opportunity } }) {
  const opp = evt.added?.element
  if (!opp || opp.stage === toStage) return
  const fromStage = opp.stage
  if (!canMove(fromStage, toStage)) {
    toast.error(t('opportunities.moveFailed'))
    await fetchBoard()
    return
  }
  if ((toStage === 'won' || toStage === 'lost') && !confirm(t('opportunities.confirmClose', { stage: toStage }))) {
    await fetchBoard()
    return
  }
  movingId.value = opp.id
  try {
    const updated = await opportunityService.updateStage(opp.customer_id, opp.id, toStage)
    const idx = cards.value.findIndex(o => o.id === opp.id)
    if (idx >= 0) cards.value[idx] = { ...updated, customer: cards.value[idx]!.customer }
    if (toStage === 'won') toast.success(t('opportunities.converted'))
  } catch {
    toast.error(t('opportunities.moveFailed'))
    await fetchBoard()
  } finally {
    movingId.value = null
  }
}

function goToCustomer(opp: Opportunity) {
  if (!can('view-customers')) return
  router.push({ name: 'customers.show', params: { id: opp.customer_id } })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function onSearchInput(e: Event) {
  searchQuery.value = (e.target as HTMLInputElement).value
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchBoard, 400)
}

onMounted(async () => {
  try {
    // Users list needs view-users; everyone else uses the assignable
    // endpoint (view-assignable-users) so the assignee filter still works.
    const res = can('view-users')
      ? await userService.list({ per_page: 100 })
      : await userService.assignable({ per_page: 100 })
    users.value = res.data ?? []
  } catch { /* non-admins may lack permission */ }
  await fetchBoard()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h2 class="mb-0">{{ t('opportunities.title') }} — {{ t('opportunities.board') }}</h2>
      <div class="d-flex gap-2">
        <BaseButton variant="outline-secondary" icon="bi-table" :to="{ name: 'opportunities.table' }">
          {{ t('opportunities.table') }}
        </BaseButton>
        <BaseButton
          v-if="can('create-opportunities')"
          variant="primary"
          icon="bi-plus-lg"
          :to="{ name: 'opportunities.createGlobal' }"
        >
          {{ t('opportunities.newOpportunity') }}
        </BaseButton>
      </div>
    </div>

    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
      <input
        class="form-control form-control-sm"
        style="width: 200px;"
        :placeholder="t('common.search')"
        @input="onSearchInput"
      />
      <select v-model="assigneeFilter" class="form-select form-select-sm" style="width: auto;" @change="fetchBoard">
        <option value="">{{ t('opportunities.allAssignees') }}</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <label class="small text-muted mb-0">{{ t('opportunities.dateFrom') }}</label>
      <input v-model="dateFrom" type="date" class="form-control form-control-sm" style="width: auto;" :aria-label="t('opportunities.dateFrom')" @change="fetchBoard" />
      <label class="small text-muted mb-0">{{ t('opportunities.dateTo') }}</label>
      <input v-model="dateTo" type="date" class="form-control form-control-sm" style="width: auto;" :aria-label="t('opportunities.dateTo')" @change="fetchBoard" />
      <div class="form-check form-switch ms-1">
        <input v-model="showClosed" class="form-check-input" type="checkbox" role="switch" @change="fetchBoard" />
        <label class="form-check-label small">{{ t('opportunities.showClosed') }}</label>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <div v-else class="board d-flex gap-3 overflow-auto pb-3">
      <div
        v-for="stage in visibleStages"
        :key="stage"
        class="board-column rounded p-2 flex-shrink-0"
        :class="{ 'board-column--closed': stage === 'won' || stage === 'lost' }"
      >
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
          <BaseBadge :variant="OPPORTUNITY_STAGE_COLORS[stage]" pill>
            {{ t(`opportunities.stages.${stage}`) }} ({{ columns[stage]!.length }})
          </BaseBadge>
          <small class="text-muted">{{ columnTotals[stage]!.toLocaleString() }}</small>
        </div>

        <draggable
          :list="columns[stage]!"
          :group="{ name: 'opportunities', pull: true, put: true }"
          item-key="id"
          class="board-list"
          :disabled="!can('update-opportunities')"
          @change="onDrop(stage, $event)"
        >
          <template #item="{ element }">
            <div class="card mb-2 board-card" :class="{ 'opacity-50': movingId === (element as Opportunity).id }">
              <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                  <router-link
                    :to="{ name: 'opportunities.show', params: { customerId: (element as Opportunity).customer_id, id: (element as Opportunity).id } }"
                    class="fw-semibold small text-body text-decoration-none flex-grow-1"
                  >
                    {{ (element as Opportunity).title }}
                  </router-link>
                  <router-link
                    :to="{ name: 'opportunities.show', params: { customerId: (element as Opportunity).customer_id, id: (element as Opportunity).id } }"
                    class="btn btn-link btn-sm p-0 text-decoration-none flex-shrink-0"
                    :title="t('common.view')"
                  >
                    <i class="bi bi-box-arrow-up-right"></i>
                  </router-link>
                </div>
                <button
                  v-if="can('view-customers')"
                  class="btn btn-link btn-sm p-0 text-decoration-none text-muted small d-block text-start"
                  @click="goToCustomer(element as Opportunity)"
                >
                  {{ (element as Opportunity).customer?.display_name ?? `#${(element as Opportunity).customer_id}` }}
                </button>
                <small
                  v-else
                  class="text-muted small d-block text-start"
                >
                  {{ (element as Opportunity).customer?.display_name ?? `#${(element as Opportunity).customer_id}` }}
                </small>
                <div class="d-flex justify-content-between align-items-center mt-2">
                  <small class="fw-semibold">
                    {{ (element as Opportunity).amount ? `${(element as Opportunity).currency} ${Number((element as Opportunity).amount).toLocaleString()}` : '—' }}
                  </small>
                  <UserAvatar
                    :name="(element as Opportunity).assigned_to?.name"
                    :id="(element as Opportunity).assigned_to?.id"
                    :size="24"
                  />
                </div>
                <small v-if="(element as Opportunity).expected_close_date" class="text-muted d-block mt-1">
                  <i class="bi bi-calendar3 me-1"></i>{{ new Date((element as Opportunity).expected_close_date as string).toLocaleDateString() }}
                </small>
              </div>
            </div>
          </template>
        </draggable>
      </div>
    </div>
  </div>
</template>

<style scoped>
.board-column {
  width: 280px;
  min-height: 200px;
  background: #edf0f7;
  border: 1px solid #e0e5ef;
}
.board-column--closed {
  background: #e6eaf2;
  border-color: #d8deea;
}
.board-list {
  min-height: 120px;
}
.board-card {
  cursor: grab;
}
.board-card:active {
  cursor: grabbing;
}
</style>
