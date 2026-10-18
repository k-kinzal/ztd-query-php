<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendDigest
{
    public function __construct(public int $userId, public string $period)
    {
    }
}
