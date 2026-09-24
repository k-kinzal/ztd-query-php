<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW TRIGGERS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\TriggerField::Name->label() // => 'Trigger'
 */
enum TriggerField: string implements MetadataField
{
    use TextField;

    case Name = 'Trigger';
    case Event = 'Event';
    case Table = 'Table';
    case Statement = 'Statement';
    case Timing = 'Timing';
    case Created = 'Created';
    case SqlMode = 'sql_mode';
    case Definer = 'Definer';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';

    /**
     * Creation time is a timestamp.
     */
    public function type(): string
    {
        return $this === self::Created ? 'timestamp' : 'varchar';
    }

    /**
     * Creation time is absent for triggers created before it was recorded.
     */
    public function nullability(): Nullability
    {
        return $this === self::Created ? Nullability::MaybeNull : Nullability::NotNull;
    }
}
