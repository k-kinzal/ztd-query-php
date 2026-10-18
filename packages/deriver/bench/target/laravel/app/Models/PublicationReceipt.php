<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PublicationReceipt extends Model
{
    protected $fillable = ['user_id','key','fingerprint','topic_id'];
    public function topic(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
