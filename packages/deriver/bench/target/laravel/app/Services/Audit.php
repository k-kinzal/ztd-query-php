<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{AuditEntry, User};
use Illuminate\Database\Eloquent\Model;

final class Audit
{
    public function record(User $actor, ?int $community, string $action, Model $subject, array $context = []): void
    {
        AuditEntry::create(['actor_id' => $actor->id, 'community_id' => $community, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->getKey(), 'context' => $context]);
    }
}
