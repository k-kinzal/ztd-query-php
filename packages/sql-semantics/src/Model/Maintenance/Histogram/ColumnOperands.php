<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\Histogram;

use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Keeps histogram columns attached to the single table whose distribution they describe.
 * @visibility SqlSemantics
 */
final class ColumnOperands
{
    /**
     * @param non-empty-list<ColumnReference|UnresolvedColumnReference> $columns Columns in request order
     * @throws InvalidStructure
     */
    public static function validate(TableReference $table, array $columns): void
    {
        Collections::alternatives(Collections::nonEmpty($columns), [ColumnReference::class, UnresolvedColumnReference::class]);
        foreach ($columns as $column) {
            if (count($column->name) !== 1 || ($column instanceof ColumnReference && ($column->binding->relationId !== $table->id || $column->binding->table->schema !== $table->declaration->schema || $column->binding->table->name !== $table->declaration->name))) {
                throw new InvalidStructure('Histogram columns must be unqualified references to their target table.');
            }
        }
    }
}
