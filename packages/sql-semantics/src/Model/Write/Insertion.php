<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Ordered storage destinations for an INSERT, independent of table declaration order.
 *
 * @example Reading structured effects
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t(id) VALUES(1)');
 *     $statement->insertion->explicitColumns // => true
 *
 * @visibility public
 */
final class Insertion
{
    /**
     * @param TableUse $target Destination relation
     * @param list<Expression> $columns Destination of each input position, including unresolved references
     * @param bool $explicitColumns Whether SQL supplied a target column list
     * @param bool $defaultValues Whether a single row of defaults was requested
     * @param list<\SqlSemantics\Schema\ColumnDefinition> $omittedColumns Columns supplied by defaults or generated values
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly TableUse $target,
        public readonly array $columns,
        public readonly bool $explicitColumns,
        public readonly bool $defaultValues,
        public readonly array $omittedColumns = [],
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($columns, Expression::class);
        \SqlSemantics\Model\Validation\Collections::objects($omittedColumns, \SqlSemantics\Schema\ColumnDefinition::class);
        foreach ($columns as $destination) {
            $column = Destination::column($destination);
            if ($column->binding !== null && $column->binding->relationId !== $target->id) {
                throw new InvalidStructure('An insertion column belongs to another relation.');
            }
        }
    }
}
