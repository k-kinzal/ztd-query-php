<?php

declare(strict_types=1);

namespace ZtdQuery\Connection;

use ZtdQuery\Schema\ColumnType;

/**
 * Driver-reported metadata for one result-set column.
 */
final class ResultColumn
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $name
     * @param ColumnType $type
     */
    public function __construct(
        public readonly string $name,
        public readonly ColumnType $type,
    ) {
    }
}
