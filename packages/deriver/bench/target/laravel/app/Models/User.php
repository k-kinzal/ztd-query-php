<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'suspended_until' => 'datetime'];
    }
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class)->withPivot('role')->withTimestamps();
    }
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'author_id');
    }
    public function isActive(): bool
    {
        return $this->suspended_until === null || $this->suspended_until->isPast();
    }
    public function roleIn(Community $community): ?string
    {
        return $this->communities()->whereKey($community->id)->first()?->pivot->role;
    }
    public function moderates(Community $community): bool
    {
        return $this->isActive() && in_array($this->roleIn($community), ['owner','moderator'], true);
    }
}
