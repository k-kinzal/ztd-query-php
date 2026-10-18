<?php

declare(strict_types=1);

namespace Harbor\Audit;

use Harbor\Database\Connection;

final class AuditLog
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function record(?int $actor, ?int $community, string $action, string $subject, array $details = []): void
    {
        $this->database->execute('INSERT INTO audit(actor_id,community_id,action,subject,details,created_at) VALUES (?,?,?,?,?,?)', [$actor, $community, $action, $subject, json_encode($details, JSON_THROW_ON_ERROR), time()]);
    }
}
