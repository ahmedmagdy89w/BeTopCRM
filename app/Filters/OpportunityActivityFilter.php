<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class OpportunityActivityFilter extends BaseFilter
{
    private const SORTABLE = ['created_at', 'due_date', 'reminder_at', 'type'];

    public function __construct(array $filters, private ?int $opportunityId = null)
    {
        parent::__construct($filters);
    }

    public function apply(Builder $query): Builder
    {
        if ($this->opportunityId !== null) {
            $query->where('opportunity_activities.opportunity_id', $this->opportunityId);
        }

        if (! empty($this->filters['type'])) {
            $types = is_array($this->filters['type'])
                ? $this->filters['type']
                : explode(',', (string) $this->filters['type']);

            $query->whereIn('opportunity_activities.type', $types);
        }

        if (! empty($this->filters['channel'])) {
            $channels = is_array($this->filters['channel'])
                ? $this->filters['channel']
                : explode(',', (string) $this->filters['channel']);

            $query->whereIn('opportunity_activities.channel', $channels);
        }

        if (! empty($this->filters['owner_id'])) {
            $query->where('opportunity_activities.user_id', (int) $this->filters['owner_id']);
        }

        if (! empty($this->filters['status'])) {
            $this->applyStatus($query, (string) $this->filters['status']);
        }

        if (! empty($this->filters['due_from'])) {
            $query->where('opportunity_activities.due_date', '>=', $this->filters['due_from']);
        }

        if (! empty($this->filters['due_to'])) {
            $query->where('opportunity_activities.due_date', '<=', $this->filters['due_to']);
        }

        if (! empty($this->filters['reminder_set'])) {
            if (filter_var($this->filters['reminder_set'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('opportunity_activities.reminder_at');
            } else {
                $query->whereNull('opportunity_activities.reminder_at');
            }
        }

        if (! empty($this->filters['search'])) {
            $term = $this->filters['search'];
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('opportunity_activities.subject', 'like', "%{$term}%")
                    ->orWhere('opportunity_activities.description', 'like', "%{$term}%");
            });
        }

        return $this->applySort($query, self::SORTABLE);
    }

    private function applyStatus(Builder $query, string $status): void
    {
        match ($status) {
            'completed' => $query->whereNotNull('opportunity_activities.completed_at'),
            'upcoming' => $query->whereNull('opportunity_activities.completed_at')
                ->where('opportunity_activities.due_date', '>', now()),
            'due_today' => $query->whereNull('opportunity_activities.completed_at')
                ->whereDate('opportunity_activities.due_date', today()),
            'overdue' => $query->whereNull('opportunity_activities.completed_at')
                ->where('opportunity_activities.due_date', '<', now()),
            default => null,
        };
    }
}
