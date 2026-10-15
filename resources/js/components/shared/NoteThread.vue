<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { noteService } from '@/services/noteService'
import type { Note } from '@/types'
import NoteEditor from '@/components/shared/NoteEditor.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()

const props = defineProps<{
  entityType: string
  entityId: number
}>()

const notes = ref<Note[]>([])
const loading = ref(true)
const saving = ref(false)
const replyingTo = ref<number | null>(null)

async function fetchNotes() {
  loading.value = true
  try {
    const res = await noteService.list(props.entityType, props.entityId, { per_page: 50 })
    notes.value = res.data
  } finally {
    loading.value = false
  }
}

async function addNote(body: string) {
  saving.value = true
  try {
    const note = await noteService.create(props.entityType, props.entityId, { body })
    notes.value.unshift(note)
  } finally {
    saving.value = false
  }
}

async function addReply(body: string, parentId: number) {
  saving.value = true
  try {
    const reply = await noteService.create(props.entityType, props.entityId, { body, parent_id: parentId })
    const parent = notes.value.find(n => n.id === parentId)
    if (parent) {
      if (!parent.replies) parent.replies = []
      parent.replies.push(reply)
      parent.replies_count++
    }
    replyingTo.value = null
  } finally {
    saving.value = false
  }
}

async function togglePin(note: Note) {
  const updated = await noteService.togglePin(note.id)
  const idx = notes.value.findIndex(n => n.id === note.id)
  const current = notes.value[idx]
  if (idx !== -1 && current) notes.value[idx] = { ...current, is_pinned: updated.is_pinned }
}

async function deleteNote(note: Note) {
  await noteService.delete(note.id)
  notes.value = notes.value.filter(n => n.id !== note.id)
}

onMounted(fetchNotes)
</script>

<template>
  <div>
    <!-- New note -->
    <div class="mb-3">
      <NoteEditor :loading="saving" @submit="addNote" />
    </div>

    <div v-if="loading" class="text-center py-3">
      <BaseSpinner />
    </div>

    <div v-else-if="notes.length === 0" class="text-center text-muted py-3">
      {{ t('common.noData') }}
    </div>

    <!-- Notes list -->
    <div v-else class="d-flex flex-column gap-3">
      <div
        v-for="note in notes"
        :key="note.id"
        class="border rounded p-3"
        :class="{ 'border-warning bg-warning bg-opacity-10': note.is_pinned }"
      >
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <strong>{{ note.author.name }}</strong>
            <small class="text-muted ms-2">{{ new Date(note.created_at).toLocaleString() }}</small>
            <i v-if="note.is_pinned" class="bi bi-pin-fill text-warning ms-1"></i>
          </div>
          <div class="d-flex gap-1">
            <button class="btn btn-link btn-sm p-0 text-muted" @click="togglePin(note)" :title="note.is_pinned ? 'Unpin' : 'Pin'">
              <i :class="note.is_pinned ? 'bi bi-pin-fill' : 'bi bi-pin'"></i>
            </button>
            <button class="btn btn-link btn-sm p-0 text-muted" @click="replyingTo = replyingTo === note.id ? null : note.id">
              <i class="bi bi-reply"></i>
            </button>
            <button class="btn btn-link btn-sm p-0 text-danger" @click="deleteNote(note)">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>

        <p class="mb-2 white-space-pre-wrap">{{ note.body }}</p>

        <!-- Replies -->
        <div v-if="note.replies && note.replies.length > 0" class="ms-4 border-start ps-3">
          <div v-for="reply in note.replies" :key="reply.id" class="mb-2">
            <div>
              <strong>{{ reply.author.name }}</strong>
              <small class="text-muted ms-2">{{ new Date(reply.created_at).toLocaleString() }}</small>
            </div>
            <p class="mb-0 white-space-pre-wrap">{{ reply.body }}</p>
          </div>
        </div>

        <!-- Reply form -->
        <div v-if="replyingTo === note.id" class="ms-4 mt-2">
          <NoteEditor :loading="saving" placeholder="Reply..." @submit="(body: string) => addReply(body, note.id)" />
        </div>
      </div>
    </div>
  </div>
</template>
