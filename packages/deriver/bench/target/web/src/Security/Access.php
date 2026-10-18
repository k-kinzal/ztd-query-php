<?php

declare(strict_types=1);

namespace Harbor\Security;

use Harbor\Database\Connection;
use Harbor\Http\Problem;

final class Access
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function user(int $id): array
    {
        $user = $this->database->one('SELECT * FROM users WHERE id = ? AND status = ?', [$id, 'active']);
        if ($user === null) {
            throw new Problem(401, 'Sign in with an active account.');
        }
        return $user;
    }

    public function role(int $community, ?int $user): ?string
    {
        return $this->database->one('SELECT role FROM memberships WHERE community_id = ? AND user_id = ?', [$community, $user])['role'] ?? null;
    }

    public function board(int $id, ?int $user, bool $write = false): array
    {
        $board = $this->database->one('SELECT * FROM boards WHERE id = ?', [$id]);
        if ($board === null) {
            throw new Problem(404, 'Board not found.');
        }
        $role = $this->role((int) $board['community_id'], $user);
        if (($board['visibility'] === 'members' && $role === null) || ($board['visibility'] === 'staff' && !in_array($role, ['owner', 'moderator'], true))) {
            throw new Problem(404, 'Board not found.');
        }
        if ($write) {
            $this->user($user ?? 0);
            if ($role === null || $board['archived']) {
                throw new Problem(403, 'An active board membership is required.');
            }
            if ($this->database->one('SELECT id FROM sanctions WHERE community_id = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > ?', [$board['community_id'], $user, time()]) !== null) {
                throw new Problem(403, 'Posting is temporarily restricted.');
            }
        }
        return $board;
    }

    public function moderator(int $community, int $user): void
    {
        $this->user($user);
        if (!in_array($this->role($community, $user), ['owner', 'moderator'], true)) {
            throw new Problem(403, 'Moderator access is required.');
        }
    }

    public function topic(int $id, ?int $user, bool $write = false): array
    {
        $topic = $this->database->one('SELECT t.*, b.community_id FROM topics t JOIN boards b ON b.id = t.board_id WHERE t.id = ?', [$id]);
        if ($topic === null) {
            throw new Problem(404, 'Topic not found.');
        }
        $this->board((int) $topic['board_id'], $user, $write);
        if (in_array($topic['state'], ['hidden', 'pending'], true) && $topic['author_id'] !== $user && !in_array($this->role((int) $topic['community_id'], $user), ['owner', 'moderator'], true)) {
            throw new Problem(404, 'Topic not found.');
        }
        return $topic;
    }
}
