<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A generated value with a mandatory computation and storage policy.
 *
 * @visibility public
 * @example Reading a generated column
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT GENERATED ALWAYS AS (a * 2) STORED)')->tables[0]->columns[1];
 *     $column->generation instanceof \SqlSemantics\Schema\Column\ComputedColumn // => true
 *     $column->generation->storage // => \SqlSemantics\Schema\Column\GeneratedStorage::Stored
 *     count($column->generation->expressions()) // => 1
 */
final class ComputedColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Expression $expression,
        public readonly GeneratedStorage $storage,
    ) {
    }

    /**
     * Returns the expression that defines the generated column.
     */
    #[Override]
    public function expressions(): array
    {
        return [$this->expression];
    }
}
