<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\TableDefinition;

/**
 * The particular relation occurrence and declared column a reference denotes.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\ColumnBinding $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class ColumnBinding
{
    /**
     * @param string $relationId Query-local relation occurrence, such as r0
     * @param TableDefinition $table Table declaration
     * @param ColumnDefinition $column Column declaration
     */
    public function __construct(
        public readonly string $relationId,
        public readonly TableDefinition $table,
        public readonly ColumnDefinition $column,
    ) {
    }
}
