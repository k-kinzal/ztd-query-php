<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Notification;

final class ReportResolved extends Notification
{
    public function __construct(private readonly Report $report)
    {
    }
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function toArray(object $notifiable): array
    {
        return ['report_id' => $this->report->id, 'state' => $this->report->state->value, 'resolution' => $this->report->resolution];
    }
}
