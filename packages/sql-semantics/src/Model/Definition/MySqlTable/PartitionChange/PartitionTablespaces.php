<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\Table\TablespaceAction;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;

/**
 * Discards or imports the tablespaces of selected partitions (MySQL 5.7 and later).
 * @visibility public
 * @example Importing partition tablespaces
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t IMPORT PARTITION p0, p1 TABLESPACE');
 *     $statement->alterations[0]->partitions->names // => ['p0', 'p1']
 */
final class PartitionTablespaces implements TableAlteration
{
    /**
     * Records the tablespace action and its partition selection.
     */
    public function __construct(public readonly TablespaceAction $action, public readonly AllPartitions|NamedPartitions $partitions)
    {
    }
}
