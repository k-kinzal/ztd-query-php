<?php

declare(strict_types=1);

namespace Harbor\Notification;

use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;

final class Inbox
{
    public function __construct(private readonly Connection $database, private readonly Access $access)
    {
    }

    public function deliver(int $limit = 100): int
    {
        return $this->database->transaction(function () use ($limit): int {
            $events = $this->database->rows('SELECT * FROM outbox WHERE delivered_at IS NULL ORDER BY id LIMIT ' . max(1, min($limit, 1000)));
            foreach ($events as $event) {
                foreach ($this->database->rows('SELECT user_id FROM subscriptions WHERE topic_id = ? AND user_id <> ?', [$event['topic_id'], $event['actor_id']]) as $subscription) {
                    try {
                        $this->access->user((int) $subscription['user_id']);
                        $this->access->topic((int) $event['topic_id'], (int) $subscription['user_id']);
                    } catch (Problem) {
                        continue;
                    }
                    $this->database->execute('INSERT INTO notifications(user_id,topic_id,event_id,message,created_at) VALUES (?,?,?,?,?) ON CONFLICT DO NOTHING', [$subscription['user_id'], $event['topic_id'], $event['id'], $event['message'], time()]);
                }
                $this->database->execute('UPDATE outbox SET delivered_at = ? WHERE id = ?', [time(), $event['id']]);
            }
            return count($events);
        });
    }

    public function list(int $user, int $before = PHP_INT_MAX): array
    {
        $this->access->user($user);
        return $this->database->rows('SELECT * FROM notifications WHERE user_id = ? AND id < ? ORDER BY id DESC LIMIT 50', [$user, $before]);
    }

    public function markRead(int $notification, int $user): void
    {
        $this->access->user($user);
        if ($this->database->execute('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ?', [time(), $notification, $user]) === 0) {
            throw new Problem(404, 'Notification not found.');
        }
    }
}
