<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{AuditEntry, OutboxEvent, PublicationReceipt};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneRetainedData extends Command
{
    protected $signature = 'harbor:prune {--dry-run}';
    protected $description = 'Expire read notifications, delivered events, request receipts and old audit records';
    public function handle(): int
    {
        $queries = ['notifications' => DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', now()->subDays(90)), 'events' => OutboxEvent::whereNotNull('delivered_at')->where('created_at', '<', now()->subDays(30)), 'receipts' => PublicationReceipt::where('created_at', '<', now()->subDay()), 'audit' => AuditEntry::where('created_at', '<', now()->subYear())];
        foreach ($queries as $name => $query) {
            $count = $this->option('dry-run') ? $query->count() : $query->delete();
            $this->line($name . ': ' . $count . ($this->option('dry-run') ? ' eligible' : ' removed'));
        }
        return self::SUCCESS;
    }
}
