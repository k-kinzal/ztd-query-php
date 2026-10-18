<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OutboxEvent;
use Illuminate\Notifications\Notification;

final class DiscussionActivity extends Notification
{
    public function __construct(private readonly OutboxEvent $event)
    {
    }
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function toArray(object $notifiable): array
    {
        return ['topic_id' => $this->event->topic_id, 'event' => $this->event->type, 'title' => $this->event->payload['title']];
    }
}
