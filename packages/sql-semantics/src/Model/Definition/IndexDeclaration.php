<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

use SqlSemantics\Schema\IndexDefinition;

/**
 * The typed definition of an index creation operation.
 *
 * @visibility public
 * @example Reading the declared index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE INDEX ix ON t((id+1)) WHERE id>0');
 *     $statement->index->definition->name // => 'ix'
 */
final class IndexDeclaration
{
    /**
     * Records the index's target, keys, predicate and storage properties.
     */
    public function __construct(public readonly IndexDefinition $definition)
    {
    }
}
