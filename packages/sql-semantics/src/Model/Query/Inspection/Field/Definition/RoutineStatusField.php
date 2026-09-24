<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW FUNCTION STATUS and SHOW PROCEDURE STATUS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineStatusField::Database->label() // => 'Db'
 */
enum RoutineStatusField: string implements MetadataField
{
    use TextField;

    case Database = 'Db';
    case Name = 'Name';
    case Type = 'Type';
    case Definer = 'Definer';
    case Modified = 'Modified';
    case Created = 'Created';
    case SecurityType = 'Security_type';
    case Comment = 'Comment';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';

    /**
     * Maintenance instants are timestamps.
     */
    public function type(): string
    {
        return match ($this) {
            self::Modified, self::Created => 'datetime',
            self::Database, self::Name, self::Type, self::Definer, self::SecurityType, self::Comment, self::CharacterSetClient, self::CollationConnection, self::DatabaseCollation => 'varchar',
        };
    }
}
