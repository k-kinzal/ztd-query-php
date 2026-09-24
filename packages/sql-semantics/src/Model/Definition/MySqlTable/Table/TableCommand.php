<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;

/**
 * An alteration without operands; tablespace commands stand alone and UPGRADE PARTITIONING exists only in MySQL 5.7.
 * @visibility public
 * @example Reading operand-free alterations
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT PRIMARY KEY)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP PRIMARY KEY, DISABLE KEYS, FORCE');
 *     $statement->alterations // => [\SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand::DropPrimaryKey, \SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand::DisableKeys, \SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand::Force]
 */
enum TableCommand: string implements TableAlteration
{
    case DropPrimaryKey = 'DROP PRIMARY KEY';
    case EnableKeys = 'ENABLE KEYS';
    case DisableKeys = 'DISABLE KEYS';
    case Force = 'FORCE';
    case RemovePartitioning = 'REMOVE PARTITIONING';
    case UpgradePartitioning = 'UPGRADE PARTITIONING';
    case DiscardTablespace = 'DISCARD TABLESPACE';
    case ImportTablespace = 'IMPORT TABLESPACE';
}
