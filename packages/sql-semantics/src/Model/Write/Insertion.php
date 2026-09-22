<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

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
     * @var list<\SqlSemantics\Schema\ColumnDefinition> Columns not addressed by an explicit input position
     */
    public readonly array $omittedColumns;

    /**
     * @param TableUse $target Destination relation
     * @param list<Storage\Path> $columns Destination of each input position, including unresolved references
     * @param bool $explicitColumns Whether SQL supplied a target column list
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly TableUse $target,
        public readonly array $columns,
        public readonly bool $explicitColumns,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($columns, Storage\Path::class);
        $addressed = [];
        foreach ($columns as $destination) {
            $column = $destination->column();
            $binding = $column->columnBinding();
            if ($binding !== null && ($binding->relationId !== $target->id || $binding->table->schema !== $target->declaration->schema || $binding->table->name !== $target->declaration->name)) {
                throw new InvalidStructure('An insertion column belongs to another relation.');
            }
            $addressed[] = $binding?->column->name ?? ($column->referenceParts()[count($column->referenceParts()) - 1] ?? '');
        }
        $this->omittedColumns = array_values(array_filter($target->declaration->columns, static fn ($column): bool => !in_array($column->name, $addressed, true)));
    }
}
