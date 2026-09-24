<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks selected partitions with explicitly requested checks.
 * @visibility public
 * @example Checking every partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t CHECK PARTITION ALL QUICK');
 *     $statement->alterations[0]->options // => [\SqlSemantics\Model\Maintenance\MySql\CheckOption::Quick]
 */
final class CheckPartitions implements TableAlteration
{
    /**
     * @param list<CheckOption> $options Requested checks in SQL order
     * @throws InvalidStructure
     */
    public function __construct(public readonly AllPartitions|NamedPartitions $partitions, public readonly array $options = [])
    {
        Collections::objects($options, CheckOption::class);
    }
}
