<?php

declare(strict_types=1);

use App\Models\{Topic, User};
use Illuminate\Support\Facades\{Broadcast, Gate};

Broadcast::channel('topics.{topicId}', function (User $user, int $topicId): bool {
    $topic = Topic::find($topicId);
    return $topic !== null && Gate::forUser($user)->allows('view', $topic);
});
