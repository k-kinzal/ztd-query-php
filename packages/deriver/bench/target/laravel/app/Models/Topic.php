<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Topic extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['board_id','author_id','title','state','version','last_posted_at'];
    protected function casts(): array
    {
        return ['state' => \App\Enums\TopicState::class, 'last_posted_at' => 'datetime', 'version' => 'integer'];
    }
    public function board(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Post::class);
    }
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
    public function subscribers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriptions')->withTimestamps();
    }
}
