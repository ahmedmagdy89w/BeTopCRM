<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use App\Notifications\MentionedInNoteNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class NoteService
{
    public function listForEntity(Model $entity, array $filters = []): LengthAwarePaginator
    {
        $query = $entity->notes()
            ->topLevel()
            ->with(['author:id,name', 'replies.author:id,name'])
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(Model $entity, array $data, User $user): Note
    {
        $note = $entity->notes()->create([
            'user_id'   => $user->id,
            'body'      => $data['body'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_pinned' => $data['is_pinned'] ?? false,
        ]);

        $note->load('author:id,name', 'replies.author:id,name');

        $this->processMentions($note, $user);

        return $note;
    }

    public function update(Note $note, array $data): Note
    {
        $note->update([
            'body'      => $data['body'],
            'is_pinned' => $data['is_pinned'] ?? $note->is_pinned,
        ]);

        return $note->fresh(['author:id,name']);
    }

    public function delete(Note $note): void
    {
        $note->delete();
    }

    public function togglePin(Note $note): Note
    {
        $note->update(['is_pinned' => !$note->is_pinned]);
        return $note->fresh();
    }

    private function processMentions(Note $note, User $author): void
    {
        preg_match_all('/@(\w+)/', $note->body, $matches);

        if (empty($matches[1])) {
            return;
        }

        $mentionedUsers = User::whereIn('name', $matches[1])
            ->where('id', '!=', $author->id)
            ->get();

        foreach ($mentionedUsers as $user) {
            $user->notify(new MentionedInNoteNotification($note, $author));
        }
    }
}
