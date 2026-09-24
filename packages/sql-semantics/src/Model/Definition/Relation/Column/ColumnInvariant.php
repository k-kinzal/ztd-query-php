<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared operand rules of column-level alterations.
 * @visibility SqlSemantics
 */
final class ColumnInvariant
{
    /**
     * @throws InvalidStructure
     */
    public static function column(string $column): void
    {
        CatalogInvariant::identifier($column);
    }

    /**
     * Column expressions are bound in the PostgreSQL dialect.
     * @throws InvalidStructure
     */
    public static function expression(?Expression $expression): void
    {
        if ($expression !== null && $expression->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A column alteration expression requires the PostgreSQL dialect.');
        }
    }
}
