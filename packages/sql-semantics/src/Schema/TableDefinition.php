<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * An ordered table declaration with a schema-qualified identity and integrity constraints.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $schema->tables[0]->schema // => 'public'
 *
 * @visibility public
 */
final class TableDefinition
{
    /**
     * @param string $schema Resolved schema or database name
     * @param string $name Resolved table name
     * @param list<ColumnDefinition> $columns Columns in declaration order
     * @param list<TableConstraint> $constraints Declared integrity conditions
     * @param Node $source Original CREATE TABLE syntax
     * @param bool $resolved Whether the declaration and its column set are known
     * @param list<IndexDefinition> $indexes Declared indexes in definition order
     * @param Table\Properties|null $properties Dialect-specific storage properties
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $columns,
        public readonly array $constraints,
        public readonly Node $source,
        public readonly bool $resolved = true,
        public readonly array $indexes = [],
        public readonly ?Table\Properties $properties = null,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($columns, ColumnDefinition::class);
        \SqlSemantics\Model\Validation\Collections::objects($constraints, TableConstraint::class);
        \SqlSemantics\Model\Validation\Collections::objects($indexes, IndexDefinition::class);
        $dialect = $properties?->dialect() ?? ($columns[0]->type->dialect ?? null);
        foreach ($columns as $column) {
            if ($column->type->dialect !== $dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('A table declaration cannot mix SQL dialects.');
            }
        }
        if (count(array_filter($constraints, static fn (TableConstraint $constraint): bool => $constraint instanceof Constraint\PrimaryKey)) > 1) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A table can declare only one primary key.');
        }
    }
}
