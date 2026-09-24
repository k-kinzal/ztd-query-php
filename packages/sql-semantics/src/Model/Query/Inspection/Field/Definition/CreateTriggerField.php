<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW CREATE TRIGGER.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTriggerField::Name->label() // => 'Trigger'
 */
enum CreateTriggerField: string implements MetadataField
{
    use TextField;

    case Name = 'Trigger';
    case SqlMode = 'sql_mode';
    case Definition = 'SQL Original Statement';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';
    case Created = 'Created';

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
