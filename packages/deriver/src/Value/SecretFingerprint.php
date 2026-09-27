<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Protects confidential identities against offline guessing with a process-local key.
 * @visibility root
 */
final class SecretFingerprint
{
    /**
     * Process-local entropy, never included in results or persistent records.
     */
    private static ?string $key = null;

    /**
     * Produces a deterministic keyed identity within the current analysis process.
     * @param string $bytes Confidential canonical representation
     * @return string HMAC-SHA-256 identity
     * System entropy failures propagate from random_bytes().
     */
    public function digest(string $bytes): string
    {
        self::$key ??= random_bytes(32);
        return hash_hmac('sha256', $bytes, self::$key);
    }
}
