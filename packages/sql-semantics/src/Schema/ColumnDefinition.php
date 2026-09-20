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
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $catalog->tables[0]->columns[0]->nullability->value // => 'not-null'
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
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node $source,
        public readonly ?Node $defaultExpression = null,
    ) {
    }
}
