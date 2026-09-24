<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Replication;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW BINARY LOG STATUS and SHOW MASTER STATUS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogStatusField::File->label() // => 'File'
 */
enum BinaryLogStatusField: string implements MetadataField
{
    use TextField;

    case File = 'File';
    case Position = 'Position';
    case DoDatabases = 'Binlog_Do_DB';
    case IgnoreDatabases = 'Binlog_Ignore_DB';
    case ExecutedGtidSet = 'Executed_Gtid_Set';

    /**
     * The log position is an integer.
     */
    public function type(): string
    {
        return $this === self::Position ? 'bigint' : 'varchar';
    }
}
