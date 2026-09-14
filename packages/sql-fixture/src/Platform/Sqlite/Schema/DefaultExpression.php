<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

/**
 * Interprets a SQL default expression.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Interprets a DEFAULT clause while preserving SQL expressions.
     */
    public function extractDefault(string $rest): int|float|bool|string|null
    {
        if (preg_match('/\bDEFAULT\s+(.+?)(?:\s+(?:NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|COLLATE|GENERATED|AS\s*\()|$)/is', $rest, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        if (preg_match("/^['\"](.*)['\"]\s*$/s", $value, $stringMatches) === 1) {
            return $stringMatches[1];
        }

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (strtoupper($value) === 'TRUE' || $value === '1') {
            return true;
        }
        if (strtoupper($value) === 'FALSE' || $value === '0') {
            return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }
}
