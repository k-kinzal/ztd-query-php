<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\{Community, User};

final class CommunityPolicy
{
    public function update(User $user, Community $community): bool
    {
        return $user->moderates($community);
    }
    public function manageMembers(User $user, Community $community): bool
    {
        return $user->isActive() && $community->owner_id === $user->id;
    }
}
