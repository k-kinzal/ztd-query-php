<?php

declare(strict_types=1);

namespace ZtdQuery\Connection;

use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Driver-reported metadata for one result-set column.
 */
final class ResultColumn
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $name
     * @param ColumnDeclaration $type
     */
    public function __construct(
        public readonly string $name,
        public readonly ColumnDeclaration $type,
    ) {
    }
}
