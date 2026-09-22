<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

use SqlSemantics\Schema\TableDefinition;

/**
 * A table declaration whose columns and constraints own their typed expressions.
 *
 * @visibility public
 */
final class TableDeclaration
{
    /**
     * Records the complete semantic declaration without parallel expression maps.
     */
    public function __construct(public readonly TableDefinition $table)
    {
    }
}
