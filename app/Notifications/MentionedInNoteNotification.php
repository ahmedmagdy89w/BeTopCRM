<?php

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionedInNoteNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Note $note,
        private User $mentionedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $entityType = class_basename($this->note->notable_type);
        $entityId   = $this->note->notable_id;
        $excerpt    = mb_substr($this->note->body, 0, 100);

        return [
            'type'        => 'mentioned_in_note',
            'message'     => "{$this->mentionedBy->name} mentioned you in a note on {$entityType} #{$entityId}: '{$excerpt}'",
            'note_id'     => $this->note->id,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'mentioned_by' => [
                'id'   => $this->mentionedBy->id,
                'name' => $this->mentionedBy->name,
            ],
        ];
    }
}
