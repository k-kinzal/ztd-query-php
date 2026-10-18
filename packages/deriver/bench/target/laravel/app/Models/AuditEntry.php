<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditEntry extends Model
{
    protected $fillable = ['actor_id','community_id','action','subject_type','subject_id','context'];
    protected function casts(): array
    {
        return ['context' => 'array'];
    }
    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
