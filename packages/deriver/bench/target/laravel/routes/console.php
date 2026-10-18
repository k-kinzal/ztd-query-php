<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('harbor:dispatch-outbox')->everyMinute()->withoutOverlapping();
Schedule::command('harbor:escalate-reports')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('harbor:prune')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::job(new App\Jobs\PurgeAttachments())->everyFiveMinutes()->withoutOverlapping();
