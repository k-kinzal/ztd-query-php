<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * A declared index with ordered keys, included columns, and a partial-index predicate.
 *
 * @example Reading index definitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id) WHERE id > 0');
 *     $schema->tables[0]->indexes[0]->unique // => true
 *
 * @visibility public
 */
final class IndexDefinition
{
    /**
     * @param string $schema Resolved index namespace
     * @param string|null $name Explicit index name; null leaves naming to the database
     * @param list<string> $table Resolved schema and table name
     * @param list<IndexElement> $elements Ordered index keys
     * @param bool $unique Whether indexed values must be unique
     * @param string|null $method Declared access method
     * @param list<string> $include Non-key columns stored in the index
     * @param Node|null $predicate Partial-index WHERE expression
     * @param Node $source Complete index declaration
     * @param array<string, string|bool|list<string>> $options Visibility, storage, and dialect options
     */
    public function __construct(
        public readonly string $schema,
        public readonly ?string $name,
        public readonly array $table,
        public readonly array $elements,
        public readonly bool $unique,
        public readonly ?string $method,
        public readonly array $include,
        public readonly ?Node $predicate,
        public readonly Node $source,
        public readonly array $options = [],
    ) {
    }
}
