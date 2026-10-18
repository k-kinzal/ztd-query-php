<?php

declare(strict_types=1);

namespace Harbor\Security;

use Harbor\Database\Connection;
use Harbor\Http\Problem;

final class RateLimiter
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function consume(string $key, int $limit, int $seconds): void
    {
        $allowed = $this->database->transaction(function () use ($key, $limit, $seconds): bool {
            $now = time();
            $this->database->execute('INSERT INTO rate_limits(key,attempts,reset_at) VALUES (?,1,?) ON CONFLICT(key) DO UPDATE SET attempts = CASE WHEN reset_at <= ? THEN 1 ELSE attempts + 1 END, reset_at = CASE WHEN reset_at <= ? THEN ? ELSE reset_at END', [$key, $now + $seconds, $now, $now, $now + $seconds]);
            return $this->database->one('SELECT attempts FROM rate_limits WHERE key = ?', [$key])['attempts'] <= $limit;
        });
        if (!$allowed) {
            throw new Problem(429, 'Too many requests. Retry after the current rate window.');
        }
    }
}
