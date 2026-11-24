<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Contract;

/**
 * Interprets a decoded native SQLite type name by its ordered affinity rules.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TypeNameAffinity
{
    /**
     * Answers the effective conversion for the recorded type name.
     *
     * @return 'INTEGER'|'TEXT'|'BLOB'|'REAL'|'NUMERIC'
     */
    public function conversion(string $name): string
    {
        $upper = strtr($name, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');

        return match (true) {
            str_contains($upper, 'INT') => 'INTEGER',
            str_contains($upper, 'CHAR'), str_contains($upper, 'CLOB'), str_contains($upper, 'TEXT') => 'TEXT',
            str_contains($upper, 'BLOB') => 'BLOB',
            str_contains($upper, 'REAL'), str_contains($upper, 'FLOA'), str_contains($upper, 'DOUB') => 'REAL',
            default => 'NUMERIC',
        };
    }
}
