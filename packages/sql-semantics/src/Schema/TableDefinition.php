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
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $columns,
        public readonly array $constraints,
        public readonly Node $source,
    ) {
    }
}
