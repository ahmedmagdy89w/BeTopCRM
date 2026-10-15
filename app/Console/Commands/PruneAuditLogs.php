<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'crm:prune-audit-logs';

    protected $description = 'Delete audit logs older than the configured retention period';

    public function handle(): void
    {
        $days    = config('crm.audit_log_retention_days', 365);
        $cutoff  = now()->subDays($days);
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} audit log records older than {$days} days.");

        $webhookDeleted = WebhookDelivery::where('created_at', '<', now()->subDays(30))->delete();
        $this->info("Pruned {$webhookDeleted} webhook delivery records older than 30 days.");
    }
}
