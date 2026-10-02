<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

/**
 * What the primary key constraints of a table definition establish; a working value of one derivation.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableKey
{
    /**
     * @param list<int> $columns The positions of the columns of the first primary key, in key order
     * @param int|null $rowid The position of the column that the first primary key makes an integer primary key
     * @param int $constraints How many PRIMARY KEY constraints the definition writes
     * @param bool $autoincrement Whether the first primary key is written with AUTOINCREMENT
     */
    public function __construct(
        public readonly array $columns = [],
        public readonly ?int $rowid = null,
        public readonly int $constraints = 0,
        public readonly bool $autoincrement = false,
    ) {
    }
}
