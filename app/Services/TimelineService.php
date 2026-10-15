<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TimelineService
{
    const ENTITY_MAP = [
        'customers' => Customer::class,
        'opportunities' => Opportunity::class,
    ];

    public function forEntity(string $entityType, int $entityId, array $filters = []): LengthAwarePaginator
    {
        $modelClass = self::ENTITY_MAP[$entityType] ?? null;

        if (! $modelClass) {
            abort(404, 'Unknown entity type.');
        }

        /** @var Model $entity */
        $entity = $modelClass::findOrFail($entityId);

        $events = collect();

        $events = $events->merge($this->getActivities($entity, $entityType));
        $events = $events->merge($this->getAuditEntries($entity));
        $events = $events->merge($this->getNotes($entity));
        $events = $events->merge($this->getAttachments($entity));
        $events = $events->merge($this->getTasks($entity));
        $events = $events->merge($this->getEmailEvents($entity));
        // Apply filters
        if (! empty($filters['type'])) {
            $types = explode(',', $filters['type']);
            $events = $events->whereIn('type', $types);
        }

        if (! empty($filters['from'])) {
            $events = $events->where('timestamp', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $events = $events->where('timestamp', '<=', $filters['to'].' 23:59:59');
        }

        if (! empty($filters['user_id'])) {
            $events = $events->filter(fn ($e) => ($e['user']['id'] ?? null) == $filters['user_id']);
        }

        $sorted = $events->sortByDesc('timestamp')->values();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);
        $total = $sorted->count();
        $items = $sorted->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
        ]);
    }

    private function getActivities(Model $entity, string $entityType): Collection
    {
        $events = collect();

        if ($entity instanceof Customer) {
            $activities = $entity->activities()->with('user:id,name')->get();
            foreach ($activities as $a) {
                $events->push([
                    'id' => "activity_{$a->id}",
                    'type' => 'activity',
                    'subtype' => $a->type,
                    'title' => ucfirst($a->type),
                    'description' => $a->description,
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                    'metadata' => ['outcome' => $a->outcome ?? null],
                    'timestamp' => $a->created_at->toIso8601String(),
                ]);
            }
        } elseif ($entity instanceof Opportunity) {
            $activities = $entity->activities()->with('user:id,name')->get();
            foreach ($activities as $a) {
                $events->push([
                    'id' => "activity_{$a->id}",
                    'type' => 'activity',
                    'subtype' => $a->type,
                    'title' => ucfirst($a->type),
                    'description' => $a->description ?? $a->subject,
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                    'metadata' => ['outcome' => $a->outcome ?? null],
                    'timestamp' => $a->created_at->toIso8601String(),
                ]);
            }
            $events = $events->merge($this->getOpportunityStages($entity));
            $events = $events->merge($this->getQuotationEvents($entity));
            $events = $events->merge($this->getInvoiceEvents($entity));
        }

        return $events;
    }

    private function getOpportunityStages(Opportunity $opportunity): Collection
    {
        $events = collect();
        $logs = AuditLog::where('auditable_type', Opportunity::class)
            ->where('auditable_id', $opportunity->id)
            ->where('action', 'updated')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($logs as $log) {
            if (isset($log->new_values['stage'])) {
                $events->push([
                    'id' => "opp_stage_{$log->id}",
                    'type' => 'stage_change',
                    'title' => 'Opportunity stage changed',
                    'description' => ($log->old_values['stage'] ?? '?').' → '.$log->new_values['stage'],
                    'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                    'metadata' => [],
                    'timestamp' => $log->created_at->toIso8601String(),
                ]);
            }
        }

        return $events;
    }

    private function getQuotationEvents(Opportunity $opportunity): Collection
    {
        return $opportunity->quotations()
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->with('sender:id,name')
            ->get()
            ->map(fn (Quotation $quotation): array => [
                'id' => "quotation_sent_{$quotation->id}",
                'type' => 'quotation',
                'subtype' => 'sent',
                'title' => __('messages.quotation_sent'),
                'description' => $quotation->quotation_number,
                'user' => $quotation->sender ? ['id' => $quotation->sender->id, 'name' => $quotation->sender->name] : null,
                'metadata' => ['quotation_id' => $quotation->id, 'total' => $quotation->total],
                'timestamp' => $quotation->sent_at->toIso8601String(),
            ]);
    }

    private function getInvoiceEvents(Opportunity $opportunity): Collection
    {
        return $opportunity->invoice()->get()->map(fn (Invoice $invoice): array => [
            'id' => "invoice_created_{$invoice->id}",
            'type' => 'invoice',
            'subtype' => 'created',
            'title' => __('messages.invoice_created'),
            'description' => $invoice->invoice_number,
            'user' => $invoice->creator ? ['id' => $invoice->creator->id, 'name' => $invoice->creator->name] : null,
            'metadata' => ['invoice_id' => $invoice->id, 'quotation_id' => $invoice->quotation_id, 'total' => $invoice->total],
            'timestamp' => $invoice->created_at->toIso8601String(),
        ]);
    }

    private function getAuditEntries(Model $entity): Collection
    {
        $events = collect();
        $morphClass = get_class($entity);

        $entries = AuditLog::where('auditable_type', $morphClass)
            ->where('auditable_id', $entity->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        foreach ($entries as $log) {
            $type = match ($log->action) {
                'created' => 'created',
                'deleted' => 'deleted',
                default => 'audit',
            };

            // Detect status changes
            if ($log->action === 'updated' &&
                isset($log->new_values['status']) &&
                ($log->old_values['status'] ?? null) !== $log->new_values['status']) {
                $type = 'status_change';
                $events->push([
                    'id' => "audit_{$log->id}",
                    'type' => $type,
                    'title' => 'Status changed',
                    'description' => ($log->old_values['status'] ?? '?').' → '.$log->new_values['status'],
                    'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                    'metadata' => ['old_value' => $log->old_values['status'] ?? null, 'new_value' => $log->new_values['status']],
                    'timestamp' => $log->created_at->toIso8601String(),
                ]);

                continue;
            }

            // Detect assignment
            if ($log->action === 'updated' &&
                isset($log->new_values['assigned_to']) &&
                ($log->old_values['assigned_to'] ?? null) !== $log->new_values['assigned_to']) {
                $events->push([
                    'id' => "audit_{$log->id}",
                    'type' => 'assigned',
                    'title' => 'Assigned',
                    'description' => null,
                    'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                    'metadata' => ['assigned_to' => $log->new_values['assigned_to']],
                    'timestamp' => $log->created_at->toIso8601String(),
                ]);

                continue;
            }

            $events->push([
                'id' => "audit_{$log->id}",
                'type' => $type,
                'title' => ucfirst($log->action),
                'description' => null,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'metadata' => ['old_values' => $log->old_values, 'new_values' => $log->new_values],
                'timestamp' => $log->created_at->toIso8601String(),
            ]);
        }

        return $events;
    }

    private function getNotes(Model $entity): Collection
    {
        $events = collect();

        $notes = $entity->notes()->with('author:id,name')->withCount('replies')->orderByDesc('created_at')->limit(100)->get();

        foreach ($notes as $note) {
            $events->push([
                'id' => "note_{$note->id}",
                'type' => 'note',
                'subtype' => $note->parent_id ? 'reply' : 'comment',
                'title' => 'Note added',
                'description' => mb_substr($note->body, 0, 200),
                'user' => $note->author ? ['id' => $note->author->id, 'name' => $note->author->name] : null,
                'metadata' => ['is_pinned' => $note->is_pinned, 'replies_count' => $note->replies_count],
                'timestamp' => $note->created_at->toIso8601String(),
            ]);
        }

        return $events;
    }

    private function getAttachments(Model $entity): Collection
    {
        $events = collect();
        $morphClass = get_class($entity);

        $attachments = Attachment::where('attachable_type', $morphClass)
            ->where('attachable_id', $entity->id)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($attachments as $att) {
            $events->push([
                'id' => "attachment_{$att->id}",
                'type' => 'attachment',
                'subtype' => 'upload',
                'title' => 'File uploaded',
                'description' => $att->original_filename ?? $att->filename,
                'user' => $att->uploader ? ['id' => $att->uploader->id, 'name' => $att->uploader->name] : null,
                'metadata' => ['filename' => $att->original_filename ?? $att->filename],
                'timestamp' => $att->created_at->toIso8601String(),
            ]);
        }

        return $events;
    }

    private function getTasks(Model $entity): Collection
    {
        $events = collect();
        $morphClass = get_class($entity);

        $tasks = Task::where('taskable_type', $morphClass)
            ->where('taskable_id', $entity->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($tasks as $task) {
            $events->push([
                'id' => "task_created_{$task->id}",
                'type' => 'task',
                'subtype' => 'created',
                'title' => 'Task created',
                'description' => $task->title,
                'user' => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name] : null,
                'metadata' => ['priority' => $task->priority, 'due_date' => $task->due_date?->toDateString()],
                'timestamp' => $task->created_at->toIso8601String(),
            ]);

            if ($task->completed_at) {
                $events->push([
                    'id' => "task_completed_{$task->id}",
                    'type' => 'task',
                    'subtype' => 'completed',
                    'title' => 'Task completed',
                    'description' => $task->title,
                    'user' => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name] : null,
                    'metadata' => [],
                    'timestamp' => $task->completed_at->toIso8601String(),
                ]);
            }
        }

        return $events;
    }

    private function getEmailEvents(Model $entity): Collection
    {
        $events = collect();

        $email = null;
        if ($entity instanceof Customer) {
            $email = $entity->email ?? $entity->contact_email ?? null;
        }

        if (! $email) {
            return $events;
        }

        $recipients = CampaignRecipient::where('email', $email)
            ->with('campaign:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($recipients as $r) {
            if ($r->sent_at) {
                $events->push([
                    'id' => "email_sent_{$r->id}",
                    'type' => 'email',
                    'subtype' => 'sent',
                    'title' => 'Campaign email sent',
                    'description' => $r->campaign?->name,
                    'user' => null,
                    'metadata' => [],
                    'timestamp' => $r->sent_at->toIso8601String(),
                ]);
            }
            if ($r->opened_at) {
                $events->push([
                    'id' => "email_opened_{$r->id}",
                    'type' => 'email',
                    'subtype' => 'opened',
                    'title' => 'Email opened',
                    'description' => $r->campaign?->name,
                    'user' => null,
                    'metadata' => [],
                    'timestamp' => $r->opened_at->toIso8601String(),
                ]);
            }
            if ($r->clicked_at) {
                $events->push([
                    'id' => "email_clicked_{$r->id}",
                    'type' => 'email',
                    'subtype' => 'clicked',
                    'title' => 'Email link clicked',
                    'description' => $r->campaign?->name,
                    'user' => null,
                    'metadata' => [],
                    'timestamp' => $r->clicked_at->toIso8601String(),
                ]);
            }
        }

        return $events;
    }
}
