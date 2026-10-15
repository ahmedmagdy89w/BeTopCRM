<?php

namespace App\Console\Commands;

use App\Services\LeadScoringService;
use Illuminate\Console\Command;

class RecalculateLeadScores extends Command
{
    protected $signature = 'crm:recalculate-lead-scores';
    protected $description = 'Recalculate stale lead scores';

    public function __construct(private LeadScoringService $leadScoringService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->leadScoringService->recalculateStale();
        $this->info("Recalculated scores for {$count} lead(s).");
        return self::SUCCESS;
    }
}
