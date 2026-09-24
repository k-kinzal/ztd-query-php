<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

/**
 * A maintenance process applied to selected partitions.
 * @visibility public
 * @example Reading the process
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t OPTIMIZE PARTITION ALL');
 *     $statement->alterations[0]->process // => \SqlSemantics\Model\Definition\MySqlTable\PartitionChange\PartitionProcess::Optimize
 */
enum PartitionProcess: string
{
    case Rebuild = 'REBUILD';
    case Optimize = 'OPTIMIZE';
    case Analyze = 'ANALYZE';
}
