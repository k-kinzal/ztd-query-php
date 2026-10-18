<?php

declare(strict_types=1);

namespace Harbor\Discussion;

use Harbor\Database\Connection;
use Harbor\Security\Access;

final class Reading
{
    public function __construct(private readonly Connection $database, private readonly Access $access)
    {
    }

    public function communities(): array
    {
        return $this->database->rows('SELECT id,slug,name,description FROM communities ORDER BY name,id LIMIT 100');
    }

    public function boards(int $community, ?int $user): array
    {
        $role = $this->access->role($community, $user);
        $visible = $role === null ? ['public'] : (in_array($role, ['owner','moderator'], true) ? ['public','members','staff'] : ['public','members']);
        $placeholders = implode(',', array_fill(0, count($visible), '?'));
        return $this->database->rows('SELECT * FROM boards WHERE community_id = ? AND visibility IN (' . $placeholders . ') ORDER BY title,id', [$community, ...$visible]);
    }

    public function topics(int $board, ?int $user, int $before = PHP_INT_MAX): array
    {
        $this->access->board($board, $user);
        return $this->database->rows("SELECT t.*,u.name AS author FROM topics t JOIN users u ON u.id = t.author_id WHERE t.board_id = ? AND t.state IN ('published','locked','archived') AND t.id < ? ORDER BY t.id DESC LIMIT 50", [$board, $before]);
    }

    public function thread(int $topic, ?int $user, int $after = 0): array
    {
        $row = $this->access->topic($topic, $user);
        $row['posts'] = $this->database->rows("SELECT p.id,p.body,p.version,p.created_at,p.author_id,u.name AS author FROM posts p JOIN users u ON u.id = p.author_id WHERE p.topic_id = ? AND p.state = 'visible' AND p.id > ? ORDER BY p.id LIMIT 100", [$topic, $after]);
        return $row;
    }

    public function search(int $board, ?int $user, string $query, int $before = PHP_INT_MAX): array
    {
        $this->access->board($board, $user);
        $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($query, 0, 100)) . '%';
        return $this->database->rows("SELECT DISTINCT t.id,t.title,t.state FROM topics t JOIN posts p ON p.topic_id = t.id WHERE t.board_id = ? AND t.id < ? AND t.state IN ('published','locked','archived') AND p.state = 'visible' AND (t.title LIKE ? ESCAPE '\' OR p.body LIKE ? ESCAPE '\') ORDER BY t.id DESC LIMIT 50", [$board, $before, $escaped, $escaped]);
    }
}
