<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Attaches an index of a partition to the partitioned index.
 * @visibility public
 * @example Reading the attached index
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER INDEX t_id_idx ATTACH PARTITION t_a_id_idx');
 *     $statement->actions[0]->index->parts // => ['t_a_id_idx']
 */
final class AttachIndexPartition implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $index)
    {
        CatalogInvariant::name($index, 3);
    }
}
