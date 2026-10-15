<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskService
{
    public function __construct(private WebhookDispatcher $webhookDispatcher) {}

    public function list(array $filters, User $user): LengthAwarePaginator
    {
        $query = Task::with(['assignee:id,name', 'creator:id,name']);

        // sales_rep can only see their own tasks
        if ($user->hasRole('sales_rep') && empty($filters['user_id'])) {
            $query->where('user_id', $user->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['entity_type']) && ! empty($filters['entity_id'])) {
            $morphMap = [
                'customer' => Customer::class,
                'opportunity' => Opportunity::class,
            ];
            $type = $morphMap[$filters['entity_type']] ?? null;
            if ($type) {
                $query->where('taskable_type', $type)
                    ->where('taskable_id', $filters['entity_id']);
            }
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        if (! empty($filters['due_today'])) {
            $query->dueToday();
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['due_date', 'priority', 'status', 'created_at'])
            ? $filters['sort_by']
            : 'due_date';
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function create(array $data, User $user): Task
    {
        $data['created_by'] = $user->id;

        // Default assignee to creator if not specified
        if (empty($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        // Map entity_type/entity_id to polymorphic taskable columns
        if (! empty($data['entity_type'])) {
            $morphMap = [
                'customer' => Customer::class,
                'opportunity' => Opportunity::class,
            ];
            $data['taskable_type'] = $morphMap[$data['entity_type']] ?? $data['entity_type'];
            $data['taskable_id'] = $data['entity_id'];
            unset($data['entity_type'], $data['entity_id']);
        }

        return Task::create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->fresh(['assignee:id,name', 'creator:id,name']);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function complete(Task $task): Task
    {
        $task->markCompleted();
        $this->webhookDispatcher->dispatch('task.completed', $task->fresh()->toArray());

        return $task->fresh();
    }

    public function mySummary(User $user): array
    {
        return [
            'overdue' => Task::forUser($user->id)->overdue()->count(),
            'due_today' => Task::forUser($user->id)->dueToday()->count(),
            'upcoming_7_days' => Task::forUser($user->id)->upcoming(7)->count(),
            'in_progress' => Task::forUser($user->id)->where('status', 'in_progress')->count(),
            'total_pending' => Task::forUser($user->id)->where('status', 'pending')->count(),
        ];
    }
}
