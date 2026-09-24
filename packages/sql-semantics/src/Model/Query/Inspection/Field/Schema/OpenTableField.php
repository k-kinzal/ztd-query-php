<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW OPEN TABLES.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\OpenTableField::Database->label() // => 'Database'
 */
enum OpenTableField: string implements MetadataField
{
    use TextField;

    case Database = 'Database';
    case Table = 'Table';
    case InUse = 'In_use';
    case NameLocked = 'Name_locked';

    /**
     * Lock counters are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::InUse, self::NameLocked => 'bigint',
            self::Database, self::Table => 'varchar',
        };
    }
}
