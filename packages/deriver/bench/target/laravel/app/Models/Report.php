<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Report extends Model
{
    protected $fillable = ['post_id','reporter_id','category','reason','state','moderator_id','resolution','resolved_at','escalated_at'];
    protected function casts(): array
    {
        return ['category' => \App\Enums\ReportCategory::class, 'state' => \App\Enums\ReportState::class, 'resolved_at' => 'datetime', 'escalated_at' => 'datetime'];
    }
    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
    public function reporter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
    public function moderator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
