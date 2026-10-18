<?php

declare(strict_types=1);

namespace Harbor\Privacy;

use Harbor\Audit\AuditLog;
use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;

final class Privacy
{
    public function __construct(private readonly Connection $database, private readonly Access $access, private readonly AuditLog $audit)
    {
    }

    public function export(int $user): array
    {
        $account = $this->access->user($user);
        unset($account['password']);
        return ['account' => $account, 'memberships' => $this->database->rows('SELECT * FROM memberships WHERE user_id = ?', [$user]), 'posts' => $this->database->rows('SELECT * FROM posts WHERE author_id = ?', [$user]), 'reports' => $this->database->rows('SELECT * FROM reports WHERE reporter_id = ?', [$user])];
    }

    public function erase(int $user, string $password): void
    {
        $this->database->transaction(function () use ($user, $password): void {
            $account = $this->access->user($user);
            if (!password_verify($password, $account['password'])) {
                throw new Problem(403, 'The current password is required.');
            }
            if ($this->database->one("SELECT community_id FROM memberships WHERE user_id = ? AND role = 'owner'", [$user])) {
                throw new Problem(409, 'Transfer community ownership before erasing the account.');
            }
            $this->database->execute("UPDATE users SET email = ?,name = 'Deleted member',password = '',status = 'erased' WHERE id = ?", ['erased-' . $user . '@invalid.local', $user]);
            $this->database->execute("UPDATE topics SET title = '[removed by author]' WHERE author_id = ?", [$user]);
            $this->database->execute("UPDATE posts SET body = '[removed by author]',state = 'erased',version = version + 1 WHERE author_id = ?", [$user]);
            $this->database->execute('DELETE FROM revisions WHERE post_id IN (SELECT id FROM posts WHERE author_id = ?)', [$user]);
            foreach (['memberships','subscriptions','bookmarks','reactions','notifications','idempotency'] as $table) {
                $this->database->execute('DELETE FROM ' . $table . ' WHERE user_id = ?', [$user]);
            }
            $this->database->execute("UPDATE reports SET reason = '[removed by reporter]' WHERE reporter_id = ?", [$user]);
            $this->audit->record(null, null, 'account.erased', (string) $user);
        });
    }

    public function purge(int $now): array
    {
        return $this->database->transaction(function () use ($now): array {
            return [
                'notifications' => $this->database->execute('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?', [$now - 90 * 86400]),
                'events' => $this->database->execute('DELETE FROM outbox WHERE delivered_at IS NOT NULL AND created_at < ?', [$now - 30 * 86400]),
                'idempotency' => $this->database->execute('DELETE FROM idempotency WHERE created_at < ?', [$now - 86400]),
                'rate_limits' => $this->database->execute('DELETE FROM rate_limits WHERE reset_at < ?', [$now]),
                'audit' => $this->database->execute('DELETE FROM audit WHERE created_at < ?', [$now - 365 * 86400]),
            ];
        });
    }
}
