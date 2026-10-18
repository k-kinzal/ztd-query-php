<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeliverDiscussionEvent;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;

final class DispatchOutbox extends Command
{
    protected $signature = 'harbor:dispatch-outbox {--limit=500}';
    protected $description = 'Queue undelivered discussion events, including events missed after a process crash';
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 10000.');
            return self::INVALID;
        }
        $events = OutboxEvent::whereNull('delivered_at')->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            DeliverDiscussionEvent::dispatch($event->id);
        }
        $this->info($events->count() . ' events queued.');
        return self::SUCCESS;
    }
}
