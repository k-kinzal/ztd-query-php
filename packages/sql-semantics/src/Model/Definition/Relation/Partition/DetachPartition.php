<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Detaches a partition into a standalone table.
 * @visibility public
 * @example Reading a concurrent detach
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DETACH PARTITION t_a CONCURRENTLY');
 *     $statement->actions[0]->mode // => \SqlSemantics\Model\Definition\Relation\Partition\PartitionDetachMode::Concurrently
 */
final class DetachPartition implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $partition, public readonly PartitionDetachMode $mode = PartitionDetachMode::Immediate)
    {
        CatalogInvariant::name($partition, 3);
    }
}
