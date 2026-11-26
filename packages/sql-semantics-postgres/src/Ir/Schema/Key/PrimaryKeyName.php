<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;

/**
 * Computes the default index name inside a fresh, closed PostgreSQL namespace.
 *
 * @visibility SqlSemantics
 */
final class PrimaryKeyName
{
    /**
     * Finds the first unoccupied name in native suffix order.
     * @param array<array-key, CreateTable|TableName|true> $occupied Actual earlier relation and backing-index occupants
     */
    public static function allocate(Identifier $table, array $occupied): string
    {
        $pass = 0;
        do {
            $suffix = $pass === 0 ? '_pkey' : '_pkey' . $pass;
            $length = min(strlen($table->value), 63 - strlen($suffix));
            while ($length < strlen($table->value) && (ord($table->value[$length]) & 0xc0) === 0x80) {
                --$length;
            }
            $candidate = substr($table->value, 0, $length) . $suffix;
            ++$pass;
        } while (isset($occupied[$candidate]));

        return $candidate;
    }
}
