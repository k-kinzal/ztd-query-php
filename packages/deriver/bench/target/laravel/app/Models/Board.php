<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Board extends Model
{
    protected $fillable = ['community_id','slug','name','description','visibility','requires_review','archived_at'];
    protected function casts(): array
    {
        return ['visibility' => \App\Enums\BoardVisibility::class, 'requires_review' => 'boolean', 'archived_at' => 'datetime'];
    }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
    public function topics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?User $user): void
    {
        $query->where(function ($query) use ($user): void {
            $query->where('visibility', \App\Enums\BoardVisibility::Public->value);
            if ($user !== null && $user->isActive()) {
                $query->orWhere(function ($query) use ($user): void {
                    $query->where('visibility', 'members')->whereHas('community.members', fn ($members) => $members->whereKey($user->id));
                })->orWhere(function ($query) use ($user): void {
                    $query->where('visibility', 'staff')->whereHas('community.members', fn ($members) => $members->whereKey($user->id)->whereIn('community_user.role', ['owner','moderator']));
                });
            }
        });
    }
}
