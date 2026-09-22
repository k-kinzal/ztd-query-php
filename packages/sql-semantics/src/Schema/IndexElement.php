<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * One ordered index key, including expression, collation, and ordering.
 *
 * @example Reading index keys
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE INDEX ix ON t(id DESC)');
 *     $schema->tables[0]->indexes[0]->elements[0]->direction // => 'DESC'
 *
 * @visibility public
 */
final class IndexElement
{
    /**
     * @param string|null $column Resolved column name, absent for an expression key
     * @param Node|null $expression Expression syntax, absent for a simple column key
     * @param string|null $direction Explicit ASC or DESC
     * @param string|null $nulls Explicit FIRST or LAST
     * @param list<string> $collation Qualified collation name
     * @param list<string> $operatorClass PostgreSQL operator class
     * @param int|null $prefixLength MySQL indexed prefix length
     * @param Node $source Complete key syntax
     * @param array<string, string|bool|list<string>> $options Operator-class parameters
     */
    public function __construct(
        public readonly ?string $column,
        public readonly ?Node $expression,
        public readonly ?string $direction,
        public readonly ?string $nulls,
        public readonly array $collation,
        public readonly array $operatorClass,
        public readonly ?int $prefixLength,
        public readonly Node $source,
        public readonly array $options = [],
    ) {
    }
}
