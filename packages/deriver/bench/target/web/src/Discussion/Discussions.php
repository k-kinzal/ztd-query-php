<?php

declare(strict_types=1);

namespace Harbor\Discussion;

use Harbor\Audit\AuditLog;
use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;
use Harbor\Validation\Input;

final class Discussions
{
    public function __construct(private readonly Connection $database, private readonly Access $access, private readonly AuditLog $audit, private readonly PublishingPolicy $policy)
    {
    }

    public function publish(int $board, int $user, array $input, string $key): int
    {
        $title = Input::text($input, 'title', 5, 200);
        $body = Input::text($input, 'body', 10, 50000);
        $key = Input::text(['key' => $key], 'key', 8, 128);
        $fingerprint = hash('sha256', json_encode([$board, $title, $body], JSON_THROW_ON_ERROR));
        return $this->database->transaction(function () use ($board, $user, $title, $body, $key, $fingerprint): int {
            $settings = $this->access->board($board, $user, true);
            $previous = $this->database->one('SELECT * FROM idempotency WHERE user_id = ? AND key = ?', [$user, $key]);
            if ($previous !== null) {
                if ($previous['fingerprint'] !== $fingerprint) {
                    throw new Problem(409, 'Idempotency key was used for different content.');
                }
                return (int) $previous['resource_id'];
            }
            $role = $this->access->role((int) $settings['community_id'], $user);
            $state = $this->policy->initialState((bool) $settings['requires_review'], $role);
            $this->database->execute('INSERT INTO topics(board_id,author_id,title,state,created_at,updated_at) VALUES (?,?,?,?,?,?)', [$board, $user, $title, $state, time(), time()]);
            $topic = (int) $this->database->pdo->lastInsertId();
            $this->database->execute('INSERT INTO posts(topic_id,author_id,body,created_at,updated_at) VALUES (?,?,?,?,?)', [$topic, $user, $body, time(), time()]);
            $this->database->execute('INSERT INTO subscriptions VALUES (?,?)', [$topic, $user]);
            $this->database->execute('INSERT INTO idempotency VALUES (?,?,?,?,?)', [$user, $key, $fingerprint, $topic, time()]);
            $this->audit->record($user, (int) $settings['community_id'], 'topic.created', (string) $topic, ['state' => $state]);
            return $topic;
        });
    }

    public function reply(int $topic, int $user, array $input): int
    {
        $body = Input::text($input, 'body', 2, 50000);
        return $this->database->transaction(function () use ($topic, $user, $body): int {
            $discussion = $this->access->topic($topic, $user, true);
            if ($discussion['state'] !== 'published') {
                throw new Problem(409, 'This discussion is not open for replies.');
            }
            $this->database->execute('INSERT INTO posts(topic_id,author_id,body,created_at,updated_at) VALUES (?,?,?,?,?)', [$topic, $user, $body, time(), time()]);
            $post = (int) $this->database->pdo->lastInsertId();
            $this->database->execute('UPDATE topics SET updated_at = ?, version = version + 1 WHERE id = ?', [time(), $topic]);
            $this->database->execute('INSERT INTO subscriptions VALUES (?,?) ON CONFLICT DO NOTHING', [$topic, $user]);
            $this->database->execute('INSERT INTO outbox(topic_id,actor_id,message,created_at) VALUES (?,?,?,?)', [$topic, $user, $this->policy->notificationText('reply'), time()]);
            return $post;
        });
    }

    public function edit(int $post, int $user, array $input): void
    {
        $body = Input::text($input, 'body', 2, 50000);
        $reason = Input::text($input, 'reason', 3, 500);
        $version = Input::positive($input, 'version');
        $this->database->transaction(function () use ($post, $user, $body, $reason, $version): void {
            $row = $this->database->one('SELECT * FROM posts WHERE id = ?', [$post]);
            if ($row === null) {
                throw new Problem(404, 'Post not found.');
            }
            $topic = $this->access->topic((int) $row['topic_id'], $user, true);
            $role = $this->access->role((int) $topic['community_id'], $user);
            $staff = in_array($role, ['owner', 'moderator'], true);
            if ((!$staff && $row['author_id'] !== $user) || (!$staff && $topic['state'] !== 'published') || $row['state'] !== 'visible' || time() - $row['created_at'] > $this->policy->editingWindow($role)) {
                throw new Problem(403, 'This post cannot be edited by this account.');
            }
            if ($this->database->execute('UPDATE posts SET body = ?, version = version + 1, updated_at = ? WHERE id = ? AND version = ?', [$body, time(), $post, $version]) !== 1) {
                throw new Problem(409, 'The post changed; reload before editing.');
            }
            $this->database->execute('INSERT INTO revisions(post_id,editor_id,body,reason,created_at) VALUES (?,?,?,?,?)', [$post, $user, $row['body'], $reason, time()]);
            $this->audit->record($user, (int) $topic['community_id'], 'post.edited', (string) $post, ['reason' => $reason]);
        });
    }

    public function transition(int $topic, int $user, string $action, int $version): void
    {
        $this->database->transaction(function () use ($topic, $user, $action, $version): void {
            $row = $this->access->topic($topic, $user);
            $this->access->moderator((int) $row['community_id'], $user);
            $next = match ([$row['state'], $action]) {
                ['pending', 'approve'], ['locked', 'unlock'], ['hidden', 'restore'] => 'published',
                ['published', 'lock'] => 'locked',
                ['published', 'hide'], ['locked', 'hide'] => 'hidden',
                ['locked', 'archive'] => 'archived',
                default => throw new Problem(409, 'This transition is not available.'),
            };
            if ($this->database->execute('UPDATE topics SET state = ?, version = version + 1, updated_at = ? WHERE id = ? AND version = ?', [$next, time(), $topic, $version]) !== 1) {
                throw new Problem(409, 'The topic changed; reload before moderating.');
            }
            $this->database->execute('INSERT INTO outbox(topic_id,actor_id,message,created_at) VALUES (?,?,?,?)', [$topic, $user, $this->policy->notificationText($next), time()]);
            $this->audit->record($user, (int) $row['community_id'], 'topic.' . $action, (string) $topic);
        });
    }
}
