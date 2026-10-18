<?php

declare(strict_types=1);

namespace Harbor\Community;

use Harbor\Audit\AuditLog;
use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Security\Access;
use Harbor\Validation\Input;

final class Communities
{
    public function __construct(private readonly Connection $database, private readonly Access $access, private readonly AuditLog $audit)
    {
    }

    public function create(int $user, array $input): int
    {
        $this->access->user($user);
        $slug = Input::slug($input);
        $name = Input::text($input, 'name', 3, 120);
        $description = Input::text($input, 'description', 10, 5000);
        return $this->database->transaction(function () use ($user, $slug, $name, $description): int {
            if ($this->database->one('SELECT id FROM communities WHERE slug = ?', [$slug])) {
                throw new Problem(409, 'Community slug already exists.');
            }
            $this->database->execute('INSERT INTO communities(slug,name,description,owner_id,created_at) VALUES (?,?,?,?,?)', [$slug, $name, $description, $user, time()]);
            $id = (int) $this->database->pdo->lastInsertId();
            $this->database->execute('INSERT INTO memberships VALUES (?,?,?,?)', [$id, $user, 'owner', time()]);
            $this->audit->record($user, $id, 'community.created', (string) $id);
            return $id;
        });
    }

    public function join(int $community, int $user): void
    {
        $this->access->user($user);
        if (!$this->database->one('SELECT id FROM communities WHERE id = ?', [$community])) {
            throw new Problem(404, 'Community not found.');
        }
        $this->database->execute('INSERT INTO memberships VALUES (?,?,?,?) ON CONFLICT DO NOTHING', [$community, $user, 'member', time()]);
    }

    public function changeRole(int $community, int $actor, int $member, string $role): void
    {
        Input::choice(['role' => $role], 'role', ['member', 'moderator']);
        $this->database->transaction(function () use ($community, $actor, $member, $role): void {
            if ($this->access->role($community, $actor) !== 'owner') {
                throw new Problem(403, 'Only the owner can change membership roles.');
            }
            if ($this->access->role($community, $member) === 'owner') {
                throw new Problem(409, 'The owner cannot be demoted.');
            }
            if ($this->database->execute('UPDATE memberships SET role = ? WHERE community_id = ? AND user_id = ?', [$role, $community, $member]) === 0) {
                throw new Problem(404, 'Member not found.');
            }
            $this->audit->record($actor, $community, 'membership.role_changed', (string) $member, ['role' => $role]);
        });
    }

    public function createBoard(int $community, int $actor, array $input): int
    {
        $slug = Input::slug($input);
        $title = Input::text($input, 'title', 3, 120);
        $visibility = Input::choice($input, 'visibility', ['public', 'members', 'staff']);
        return $this->database->transaction(function () use ($community, $actor, $slug, $title, $visibility, $input): int {
            $this->access->moderator($community, $actor);
            if ($this->database->one('SELECT id FROM boards WHERE community_id = ? AND slug = ?', [$community, $slug])) {
                throw new Problem(409, 'Board slug already exists.');
            }
            $this->database->execute('INSERT INTO boards(community_id,slug,title,visibility,requires_review) VALUES (?,?,?,?,?)', [$community, $slug, $title, $visibility, !empty($input['requires_review']) ? 1 : 0]);
            $id = (int) $this->database->pdo->lastInsertId();
            $this->audit->record($actor, $community, 'board.created', (string) $id);
            return $id;
        });
    }
    public function transfer(int $community, int $actor, int $successor): void
    {
        $this->database->transaction(function () use ($community, $actor, $successor): void {
            $this->access->user($actor);
            $this->access->user($successor);
            if ($actor === $successor || $this->access->role($community, $actor) !== 'owner' || $this->access->role($community, $successor) === null) {
                throw new Problem(409, 'The owner must transfer to another active community member.');
            }
            $this->database->execute("UPDATE memberships SET role = 'moderator' WHERE community_id = ? AND user_id = ?", [$community, $actor]);
            $this->database->execute("UPDATE memberships SET role = 'owner' WHERE community_id = ? AND user_id = ?", [$community, $successor]);
            $this->database->execute('UPDATE communities SET owner_id = ? WHERE id = ?', [$successor, $community]);
            $this->audit->record($actor, $community, 'ownership.transferred', (string) $successor);
        });
    }

    public function archiveBoard(int $boardId, int $actor, bool $archived): void
    {
        $this->database->transaction(function () use ($boardId, $actor, $archived): void {
            $board = $this->access->board($boardId, $actor);
            $this->access->moderator((int) $board['community_id'], $actor);
            $this->database->execute('UPDATE boards SET archived = ? WHERE id = ?', [$archived ? 1 : 0, $boardId]);
            $this->audit->record($actor, (int) $board['community_id'], $archived ? 'board.archived' : 'board.reopened', (string) $boardId);
        });
    }

}
