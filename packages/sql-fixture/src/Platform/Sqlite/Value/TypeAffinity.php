<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Value;

/**
 * Resolves SQLite storage affinity from a declared type name.
 *
 * @visibility root
 */
final class TypeAffinity
{
    /**
     * Determine SQLite type affinity based on declared type name.
     * See: https://www.sqlite.org/datatype3.html#type_affinity
     */
    public function determineAffinity(string $type): string
    {
        if (str_contains($type, 'INT')) {
            return 'INTEGER';
        }

        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }

        if ($type === '' || str_contains($type, 'BLOB')) {
            return 'BLOB';
        }

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }
}
