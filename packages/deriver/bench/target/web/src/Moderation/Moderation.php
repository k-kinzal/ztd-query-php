<?php

declare(strict_types=1);

namespace Harbor\Moderation;

use Harbor\Audit\AuditLog;
use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;
use Harbor\Validation\Input;

final class Moderation
{
    public function __construct(private readonly Connection $database, private readonly Access $access, private readonly AuditLog $audit)
    {
    }

    public function report(int $post, int $user, string $reason): int
    {
        $this->access->user($user);
        $reason = Input::text(['reason' => $reason], 'reason', 10, 2000);
        return $this->database->transaction(function () use ($post, $user, $reason): int {
            $row = $this->database->one("SELECT * FROM posts WHERE id = ? AND state = 'visible'", [$post]);
            if ($row === null) {
                throw new Problem(404, 'Post not found.');
            }
            $this->access->topic((int) $row['topic_id'], $user);
            $this->database->execute('INSERT INTO reports(post_id,reporter_id,reason,created_at) VALUES (?,?,?,?) ON CONFLICT DO NOTHING', [$post, $user, $reason, time()]);
            return (int) $this->database->one('SELECT id FROM reports WHERE post_id = ? AND reporter_id = ?', [$post, $user])['id'];
        });
    }

    public function queue(int $community, int $user): array
    {
        $this->access->moderator($community, $user);
        return $this->database->rows("SELECT r.*,p.body FROM reports r JOIN posts p ON p.id = r.post_id JOIN topics t ON t.id = p.topic_id JOIN boards b ON b.id = t.board_id WHERE b.community_id = ? AND r.state = 'open' ORDER BY r.created_at,r.id LIMIT 100", [$community]);
    }

    public function resolve(int $report, int $user, string $decision, string $reason): void
    {
        Input::choice(['decision' => $decision], 'decision', ['dismissed', 'upheld']);
        Input::text(['reason' => $reason], 'reason', 10, 2000);
        $this->database->transaction(function () use ($report, $user, $decision, $reason): void {
            $row = $this->database->one('SELECT r.*,p.topic_id,p.author_id,b.community_id FROM reports r JOIN posts p ON p.id = r.post_id JOIN topics t ON t.id = p.topic_id JOIN boards b ON b.id = t.board_id WHERE r.id = ?', [$report]);
            if ($row === null) {
                throw new Problem(404, 'Report not found.');
            }
            $this->access->moderator((int) $row['community_id'], $user);
            if ($row['state'] !== 'open' || $row['author_id'] === $user || $row['reporter_id'] === $user) {
                throw new Problem(409, 'A separate moderator must resolve an open report.');
            }
            $this->database->execute('UPDATE reports SET state = ?,resolution = ?,moderator_id = ?,resolved_at = ? WHERE id = ?', [$decision, $reason, $user, time(), $report]);
            if ($decision === 'upheld') {
                $this->database->execute("UPDATE posts SET state = 'hidden',version = version + 1 WHERE id = ?", [$row['post_id']]);
            }
            $this->audit->record($user, (int) $row['community_id'], 'report.' . $decision, (string) $report, ['reason' => $reason]);
        });
    }

    public function sanction(int $community, int $actor, int $user, string $reason, int $hours): int
    {
        Input::text(['reason' => $reason], 'reason', 10, 2000);
        if ($hours < 1 || $hours > 2160) {
            throw new Problem(422, 'A restriction must last between one hour and 90 days.');
        }
        return $this->database->transaction(function () use ($community, $actor, $user, $reason, $hours): int {
            $this->access->moderator($community, $actor);
            if ($this->access->role($community, $user) !== 'member') {
                throw new Problem(409, 'Only ordinary members can be restricted.');
            }
            $this->database->execute('INSERT INTO sanctions(community_id,user_id,moderator_id,reason,expires_at,created_at) VALUES (?,?,?,?,?,?)', [$community, $user, $actor, $reason, time() + $hours * 3600, time()]);
            $id = (int) $this->database->pdo->lastInsertId();
            $this->audit->record($actor, $community, 'member.restricted', (string) $user, ['sanction' => $id]);
            return $id;
        });
    }
}
