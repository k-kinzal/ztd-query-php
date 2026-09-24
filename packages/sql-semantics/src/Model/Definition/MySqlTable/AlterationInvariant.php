<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Validates the operands shared by MySQL table alterations.
 * @visibility SqlSemantics
 */
final class AlterationInvariant
{
    /**
     * Requires a nonempty identifier for a column, index, constraint, or partition.
     * @throws InvalidStructure
     */
    public static function name(string $name): void
    {
        CatalogInvariant::identifier($name);
    }

    /**
     * Requires a nonempty list of distinct nonempty names.
     * @param list<string> $names
     * @return non-empty-list<string>
     * @throws InvalidStructure
     */
    public static function names(array $names): array
    {
        $names = Collections::nonEmpty($names);
        Collections::strings($names);
        array_map(self::name(...), $names);
        if (count(array_unique(array_map(strtolower(...), $names))) !== count($names)) {
            throw new InvalidStructure('A name list cannot repeat a name.');
        }
        return $names;
    }

    /**
     * Requires a MySQL column declaration and integrity constraints.
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public static function column(ColumnDefinition $column, array $constraints): void
    {
        if ($column->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A MySQL table alteration requires a MySQL column declaration.');
        }
        self::name($column->name);
        Collections::objects($constraints, TableConstraint::class);
    }

    /**
     * Requires an expression bound in the MySQL dialect.
     * @throws InvalidStructure
     */
    public static function expression(Expression $expression): void
    {
        if ($expression->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A MySQL table alteration requires MySQL expressions.');
        }
    }

    /**
     * Requires a positive count.
     * @throws InvalidStructure
     */
    public static function positive(int $count): void
    {
        if ($count < 1) {
            throw new InvalidStructure('A partition count must be positive.');
        }
    }
}
