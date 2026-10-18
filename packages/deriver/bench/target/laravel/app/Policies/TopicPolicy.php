<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TopicState;
use App\Models\{Topic, User};

final class TopicPolicy
{
    public function view(?User $user, Topic $topic): bool
    {
        if (!(new BoardPolicy())->view($user, $topic->board)) {
            return false;
        }
        return in_array($topic->state, [TopicState::Published, TopicState::Locked, TopicState::Archived], true)
            || ($user !== null && ($user->id === $topic->author_id || $user->moderates($topic->board->community)));
    }
    public function reply(User $user, Topic $topic): bool
    {
        return $topic->state === TopicState::Published && (new BoardPolicy())->publish($user, $topic->board);
    }
    public function moderate(User $user, Topic $topic): bool
    {
        return $user->moderates($topic->board->community);
    }
}
