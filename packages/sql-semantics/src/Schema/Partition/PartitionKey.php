<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Partition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One partition key: a column of the table or an expression over its columns, with an optional collation and operator class.
 *
 * @visibility public
 * @example Reading column and expression keys
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, name TEXT) PARTITION BY RANGE (id, lower(name) COLLATE "C" text_pattern_ops)')->tables[0];
 *     $keys = $table->properties->partitioning->keys;
 *     $keys[0]->value->referenceParts() // => ['id']
 *     $keys[1]->value->structure()->toString() // => '"lower"("name")'
 *     $keys[1]->collation->parts // => ['C']
 *     $keys[1]->operatorClass->parts // => ['text_pattern_ops']
 */
final class PartitionKey
{
    /**
     * Requires a PostgreSQL value and object names within their qualification depth.
     *
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly Expression $value,
        public readonly ?QualifiedName $collation = null,
        public readonly ?QualifiedName $operatorClass = null,
    ) {
        if ($value->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A partition key requires a PostgreSQL expression.');
        }
        if ($collation !== null) {
            CatalogInvariant::name($collation, 2);
        }
        if ($operatorClass !== null) {
            CatalogInvariant::name($operatorClass, 2);
        }
    }
}
