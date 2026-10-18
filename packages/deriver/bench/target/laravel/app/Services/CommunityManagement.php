<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Board, Community, User};
use Illuminate\Support\Facades\{DB, Gate};

final class CommunityManagement
{
    public function __construct(private readonly Audit $audit)
    {
    }

    public function create(User $actor, array $input): Community
    {
        return DB::transaction(function () use ($actor, $input): Community {
            $community = Community::create($input + ['owner_id' => $actor->id]);
            $community->members()->attach($actor, ['role' => 'owner']);
            $this->audit->record($actor, $community->id, 'community.created', $community);
            return $community;
        });
    }

    public function board(Community $community, User $actor, array $input): Board
    {
        Gate::forUser($actor)->authorize('update', $community);
        return DB::transaction(function () use ($community, $actor, $input): Board {
            $board = $community->boards()->create($input);
            $this->audit->record($actor, $community->id, 'board.created', $board);
            return $board;
        });
    }

    public function role(Community $community, User $actor, User $member, string $role): void
    {
        DB::transaction(function () use ($community, $actor, $member, $role): void {
            $community = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageMembers', $community);
            abort_if($community->owner_id === $member->id, 409, 'Use ownership transfer to replace the owner.');
            abort_unless($community->members()->whereKey($member->id)->exists(), 404, 'Member not found.');
            $community->members()->updateExistingPivot($member->id, ['role' => $role]);
            $this->audit->record($actor, $community->id, 'membership.changed', $member, ['role' => $role]);
        });
    }

    public function transfer(Community $community, User $actor, User $successor): void
    {
        DB::transaction(function () use ($community, $actor, $successor): void {
            $community = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageMembers', $community);
            abort_unless($successor->isActive() && $successor->hasVerifiedEmail() && $community->members()->whereKey($successor->id)->exists(), 422, 'The successor must be a verified active member.');
            abort_if($actor->id === $successor->id, 409, 'The successor already owns this community.');
            $community->members()->updateExistingPivot($actor->id, ['role' => 'moderator']);
            $community->members()->updateExistingPivot($successor->id, ['role' => 'owner']);
            $community->update(['owner_id' => $successor->id]);
            $this->audit->record($actor, $community->id, 'ownership.transferred', $successor);
        });
    }
}
