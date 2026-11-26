<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Storage;

use SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;

/**
 * Checks the clustered record for a new ordinary strict InnoDB table on 16-KiB pages.
 *
 * @visibility SqlSemantics
 */
final class InnoDbRecord
{
    /**
     * Checks the exact maximum record with the admitted signed and unindexed VARCHAR fields.
     * @param list<TableColumn> $columns
     */
    public static function fits(array $columns, bool $hasPrimaryKey): bool
    {
        $payload = 0;
        $nullable = 0;
        foreach ($columns as $column) {
            $storage = $column->storage;
            $payload += $storage instanceof CharacterStorage ? self::varcharBytes($storage->length) : $storage->bytes();
            $nullable += (int) $column->nullable;
        }

        return self::fitsPayload($payload, $nullable, $hasPrimaryKey);
    }

    /**
     * Checks the clustered payload and its record header against the native page geometry.
     */
    public static function fitsPayload(int $payload, int $nullable, bool $hasPrimaryKey): bool
    {
        $header = $hasPrimaryKey ? 18 : 24;

        return $header + $payload + intdiv($nullable + 7, 8) < 8126;
    }

    /**
     * Computes the local variable field size in the DYNAMIC clustered record.
     */
    public static function varcharBytes(int $length): int
    {
        return min(4 * $length, 40) + 1;
    }
}
