<?php

declare(strict_types=1);

namespace HarborOps\Queue;

final class RetryPolicy
{
    public function delay(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 30, $attempt === 2 => 120, $attempt === 3 => 600, default => 3600
        };
    }
    public function exhausted(int $attempt): bool
    {
        return $attempt >= 5;
    }
    public function leaseSeconds(): int
    {
        return 300;
    }
}
