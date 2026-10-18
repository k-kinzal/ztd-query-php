<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class OutboxEvent extends Model
{
    protected $fillable = ['topic_id','actor_id','type','payload','delivered_at'];
    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime'];
    }
    public function topic(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
