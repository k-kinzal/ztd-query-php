<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

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
     * @param Expression|null $predicate Partial-index WHERE expression
     * @param Node $source Complete index declaration
     * @param Index\Properties $properties Index storage and access properties
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly string $schema,
        public readonly ?string $name,
        public readonly array $table,
        public readonly array $elements,
        public readonly bool $unique,
        public readonly ?string $method,
        public readonly array $include,
        public readonly ?Expression $predicate,
        public readonly Node $source,
        public readonly Index\Properties $properties = new Index\Properties(),
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($elements, IndexElement::class);
        \SqlSemantics\Model\Validation\Collections::strings($include);
        if ($table === [] || $elements === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An index requires a target table and at least one key.');
        }
    }
}
