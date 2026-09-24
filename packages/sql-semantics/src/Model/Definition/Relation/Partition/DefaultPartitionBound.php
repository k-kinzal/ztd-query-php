<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

/**
 * Selects the rows that no other partition accepts.
 * @visibility public
 * @example Reading a default partition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_rest DEFAULT');
 *     $statement->actions[0]->bound instanceof \SqlSemantics\Model\Definition\Relation\Partition\DefaultPartitionBound // => true
 */
final class DefaultPartitionBound
{
    /**
     * The default bound has no operands.
     */
    public function __construct()
    {
    }
}
