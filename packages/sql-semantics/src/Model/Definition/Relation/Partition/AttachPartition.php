<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Attaches an existing table as a partition covering the given bound.
 * @visibility public
 * @example Reading the attached table
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION app.t_a FOR VALUES IN (1)');
 *     $statement->actions[0]->partition->parts // => ['app', 't_a']
 */
final class AttachPartition implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $partition, public readonly HashPartitionBound|ListPartitionBound|RangePartitionBound|DefaultPartitionBound $bound)
    {
        CatalogInvariant::name($partition, 3);
    }
}
