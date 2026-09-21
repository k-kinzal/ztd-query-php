<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A column declaration, before a query can change its nullability.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $schema->tables[0]->columns[0]->nullability->value // => 'not-null'
 *
 * @visibility public
 */
final class ColumnDefinition
{
    /**
     * @param string $name Resolved column name
     * @param TypeDescriptor $type Declared database type
     * @param Nullability $nullability Declaration-level NULL allowance
     * @param Node $source Original column declaration
     * @param Node|null $defaultExpression Original default syntax, evaluated on insertion
     * @param list<Node> $attributes Complete column attributes, including collation and identity
     * @param Node|null $generatedExpression Generated value expression
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node $source,
        public readonly ?Node $defaultExpression = null,
        public readonly array $attributes = [],
        public readonly ?Node $generatedExpression = null,
    ) {
    }
}
