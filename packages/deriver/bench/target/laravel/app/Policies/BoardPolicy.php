<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BoardVisibility;
use App\Models\{Board, User};

final class BoardPolicy
{
    public function view(?User $user, Board $board): bool
    {
        return match ($board->visibility) {
            BoardVisibility::Public => true,
            BoardVisibility::Members => $user !== null && $user->isActive() && $user->roleIn($board->community) !== null,
            BoardVisibility::Staff => $user?->moderates($board->community) ?? false,
        };
    }

    public function publish(User $user, Board $board): bool
    {
        return $user->isActive() && $this->view($user, $board) && $board->archived_at === null
            && $user->roleIn($board->community) !== null
            && !$board->community->bans()->where('user_id', $user->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
    }

    public function update(User $user, Board $board): bool
    {
        return $user->moderates($board->community);
    }
}
