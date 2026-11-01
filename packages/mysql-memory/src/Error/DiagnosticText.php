<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * Bounds values displayed by server diagnostics, independently of their stored values.
 *
 * @visibility MySqlMemory
 */
final class DiagnosticText
{
    /**
     * Bounds the missing-table list, including the name in an IF EXISTS note.
     * Native 5.6/5.7 preserve complete characters within 100 bytes. Modern releases
     * keep 129 bytes and replace every byte of a partial UTF-8 character with '?'.
     */
    public function missingTables(string $names, bool $legacy): string
    {
        return $this->bytes($names, $legacy ? 100 : 129, $legacy);
    }

    /**
     * Keeps a byte prefix, discarding incomplete UTF-8 characters in legacy releases
     * and replacing each incomplete byte with a question mark in modern releases.
     *
     * @param positive-int $limit The displayed byte bound
     */
    public function bytes(string $value, int $limit, bool $legacy = false): string
    {
        $prefix = mb_strcut($value, 0, $limit, 'UTF-8');

        return $legacy ? $prefix : $prefix . str_repeat('?', min($limit, strlen($value)) - strlen($prefix));
    }
}
