<?php

declare(strict_types=1);

namespace Harbor\Discussion;

use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;
use Harbor\Validation\Input;

final class Engagement
{
    public function __construct(private readonly Connection $database, private readonly Access $access)
    {
    }

    public function subscribe(int $topic, int $user, bool $enabled): void
    {
        $this->access->user($user);
        $this->access->topic($topic, $user);
        if ($enabled) {
            $this->database->execute('INSERT INTO subscriptions VALUES (?,?) ON CONFLICT DO NOTHING', [$topic, $user]);
        } else {
            $this->database->execute('DELETE FROM subscriptions WHERE topic_id = ? AND user_id = ?', [$topic, $user]);
        }
    }

    public function bookmark(int $topic, int $user, bool $enabled): void
    {
        $this->access->user($user);
        $this->access->topic($topic, $user);
        if ($enabled) {
            $this->database->execute('INSERT INTO bookmarks VALUES (?,?,?) ON CONFLICT DO NOTHING', [$topic, $user, time()]);
        } else {
            $this->database->execute('DELETE FROM bookmarks WHERE topic_id = ? AND user_id = ?', [$topic, $user]);
        }
    }

    public function react(int $post, int $user, string $reaction): void
    {
        Input::choice(['reaction' => $reaction], 'reaction', ['helpful','thanks','agree']);
        $this->access->user($user);
        $row = $this->database->one("SELECT * FROM posts WHERE id = ? AND state = 'visible'", [$post]);
        if ($row === null) {
            throw new Problem(404, 'Post not found.');
        }
        $this->access->topic((int) $row['topic_id'], $user);
        if ($row['author_id'] === $user) {
            throw new Problem(409, 'You cannot react to your own post.');
        }
        $this->database->execute('INSERT INTO reactions VALUES (?,?,?) ON CONFLICT(post_id,user_id) DO UPDATE SET reaction = excluded.reaction', [$post, $user, $reaction]);
    }
}
