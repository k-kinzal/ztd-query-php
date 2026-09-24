<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the index that future CLUSTER operations use for this table.
 * @visibility public
 * @example Reading the clustering index
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t CLUSTER ON t_pkey');
 *     $statement->actions[0]->index // => 't_pkey'
 */
final class ClusterOn implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $index)
    {
        CatalogInvariant::identifier($index);
    }
}
