<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW EVENTS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\EventField::Database->label() // => 'Db'
 */
enum EventField: string implements MetadataField
{
    use TextField;

    case Database = 'Db';
    case Name = 'Name';
    case Definer = 'Definer';
    case TimeZone = 'Time zone';
    case Type = 'Type';
    case ExecuteAt = 'Execute at';
    case IntervalValue = 'Interval value';
    case IntervalField = 'Interval field';
    case Starts = 'Starts';
    case Ends = 'Ends';
    case Status = 'Status';
    case Originator = 'Originator';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';

    /**
     * Schedule instants are timestamps; the originator is a server identity.
     */
    public function type(): string
    {
        return match ($this) {
            self::ExecuteAt, self::Starts, self::Ends => 'datetime',
            self::Originator => 'bigint',
            self::Database, self::Name, self::Definer, self::TimeZone, self::Type, self::IntervalValue, self::IntervalField, self::Status, self::CharacterSetClient, self::CollationConnection, self::DatabaseCollation => 'varchar',
        };
    }

    /**
     * Schedule operands depend on whether the event is one-time or recurring.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::ExecuteAt, self::IntervalValue, self::IntervalField, self::Starts, self::Ends => Nullability::MaybeNull,
            self::Database, self::Name, self::Definer, self::TimeZone, self::Type, self::Status, self::Originator, self::CharacterSetClient, self::CollationConnection, self::DatabaseCollation => Nullability::NotNull,
        };
    }
}
