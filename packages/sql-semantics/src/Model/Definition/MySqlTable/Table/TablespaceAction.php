<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

/**
 * Detaches or reattaches the tablespace files of selected partitions.
 * @visibility public
 * @example Reading the tablespace action
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DISCARD PARTITION p0 TABLESPACE');
 *     $statement->alterations[0]->action // => \SqlSemantics\Model\Definition\MySqlTable\Table\TablespaceAction::Discard
 */
enum TablespaceAction: string
{
    case Discard = 'DISCARD';
    case Import = 'IMPORT';
}
