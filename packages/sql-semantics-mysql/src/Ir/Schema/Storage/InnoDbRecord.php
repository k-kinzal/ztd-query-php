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
        $payload = $hasPrimaryKey ? 18 : 24;
        $nullable = 0;
        foreach ($columns as $column) {
            $storage = $column->storage;
            $payload += $storage instanceof CharacterStorage ? min(4 * $storage->length, 40) + 1 : $storage->bytes();
            $nullable += (int) $column->nullable;
        }

        return $payload + intdiv($nullable + 7, 8) < 8126;
    }
}
