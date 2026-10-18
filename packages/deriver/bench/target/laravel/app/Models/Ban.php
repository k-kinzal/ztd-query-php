<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Ban extends Model
{
    protected $fillable = ['community_id','user_id','moderator_id','reason','expires_at','revoked_at'];
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
