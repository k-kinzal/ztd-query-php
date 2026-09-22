<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Declaration;

use SqlParser\Parser\Node;

/**
 * Internal declaration syntax awaiting name and type binding.
 *
 * @visibility SqlSemantics
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
     * @param array<string, string|bool|list<string>> $options Named table options with decoded values
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $columns,
        public readonly array $constraints,
        public readonly Node $source,
        public readonly bool $resolved = true,
        public readonly array $indexes = [],
        public readonly array $options = [],
    ) {
    }
}
