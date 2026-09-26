<?php

declare(strict_types=1);

namespace Deriver\Internal\Value;

use Deriver\Api\InvalidInputException;
use Exception;

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
     * @throws InvalidInputException If the system entropy source is unavailable
     */
    public function digest(string $bytes): string
    {
        try {
            self::$key ??= random_bytes(32);
        } catch (Exception $exception) {
            throw new InvalidInputException('Cannot initialize confidential fingerprints.', previous: $exception);
        }
        return hash_hmac('sha256', $bytes, self::$key);
    }
}
