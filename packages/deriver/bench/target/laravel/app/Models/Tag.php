<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Tag extends Model
{
    protected $fillable = ['name','slug'];
    public function topics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }
}
