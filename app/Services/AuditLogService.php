<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\EmailTemplate;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService
{
    private array $entityTypeMap = [
        'customer' => Customer::class,
        'opportunity' => Opportunity::class,
        'customer_activity' => CustomerActivity::class,
        'task' => Task::class,
        'tag' => Tag::class,
        'email_template' => EmailTemplate::class,
        'campaign' => Campaign::class,
    ];

    public function list(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::with('user:id,name')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        if (! empty($filters['entity_type'])) {
            $type = $this->entityTypeMap[$filters['entity_type']] ?? $filters['entity_type'];
            $query->where('auditable_type', $type);
        }

        if (! empty($filters['action'])) {
            $query->action($filters['action']);
        }

        $query->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);

        return $query->paginate($perPage);
    }

    public function forEntity(string $type, int $id, array $filters = []): LengthAwarePaginator
    {
        $morphType = $this->entityTypeMap[$type] ?? $type;

        $query = AuditLog::with('user:id,name')
            ->forEntity($morphType, $id)
            ->orderBy('created_at', 'desc');

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);

        return $query->paginate($perPage);
    }
}
