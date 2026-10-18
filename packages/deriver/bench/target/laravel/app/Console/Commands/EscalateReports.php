<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{AuditEntry, Report};
use App\Services\PublishingRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class EscalateReports extends Command
{
    protected $signature = 'harbor:escalate-reports';
    protected $description = 'Escalate open reports that exceed the response deadline for their category';
    public function handle(PublishingRules $rules): int
    {
        $count = 0;
        foreach (Report::where('state', 'open')->whereNull('escalated_at')->lazyById(100) as $report) {
            if ($report->created_at->gt(now()->subHours($rules->escalationHours($report->category->value)))) {
                continue;
            }
            $count += DB::transaction(function () use ($report): int {
                if (Report::whereKey($report->id)->where('state', 'open')->whereNull('escalated_at')->update(['escalated_at' => now()]) === 0) {
                    return 0;
                }
                AuditEntry::create(['actor_id' => null, 'community_id' => $report->post->topic->board->community_id, 'action' => 'report.escalated', 'subject_type' => 'Report', 'subject_id' => $report->id, 'context' => ['category' => $report->category->value]]);
                return 1;
            });
        }
        $this->info($count . ' reports escalated.');
        return self::SUCCESS;
    }
}
