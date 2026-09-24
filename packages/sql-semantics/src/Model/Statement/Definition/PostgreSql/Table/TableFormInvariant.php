<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Table;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\TableConstraint;

/**
 * Operand rules shared by the PostgreSQL table forms whose columns come from elsewhere: a partition and a typed table.
 * @visibility SqlSemantics
 */
final class TableFormInvariant
{
    /**
     * Requires PostgreSQL, overrides of distinct columns, typed constraints, and properties without INHERITS, which neither form accepts.
     *
     * @param list<PartitionColumn> $columns
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public static function check(Origin $origin, array $columns, array $constraints, PostgreSqlProperties $properties): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Partitions and typed tables require PostgreSQL.');
        }
        Collections::objects($columns, PartitionColumn::class);
        Collections::objects($constraints, TableConstraint::class);
        $names = array_map(static fn (PartitionColumn $column): string => $column->column, $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('A column is overridden at most once.');
        }
        if ($properties->parents !== []) {
            throw new InvalidStructure('Partitions and typed tables cannot declare INHERITS.');
        }
    }
}
