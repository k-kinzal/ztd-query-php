<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\DiscussionChanged;
use App\Models\OutboxEvent;
use App\Notifications\DiscussionActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB, Gate};

final class DeliverDiscussionEvent implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public function __construct(public readonly int $eventId)
    {
    }
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }
    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = OutboxEvent::whereKey($this->eventId)->lockForUpdate()->first();
            if ($event === null || $event->delivered_at !== null) {
                return;
            }
            $topic = $event->topic;
            if ($topic !== null) {
                foreach ($topic->subscribers()->where('users.id', '<>', $event->actor_id)->lazyById(100, 'users.id', 'id') as $user) {
                    if ($user->isActive() && Gate::forUser($user)->allows('view', $topic)) {
                        $user->notify(new DiscussionActivity($event));
                    }
                }
                event(new DiscussionChanged($topic->id, $topic->version));
            }
            $event->update(['delivered_at' => now()]);
        }, 3);
    }
}
