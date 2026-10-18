<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Attachment extends Model
{
    protected $fillable = ['post_id','user_id','disk','path','name','mime','size','purge_requested_at'];
    protected function casts(): array
    {
        return ['size' => 'integer', 'purge_requested_at' => 'datetime'];
    }
    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
