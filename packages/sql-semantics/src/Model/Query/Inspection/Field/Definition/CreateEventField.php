<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW CREATE EVENT.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateEventField::Name->label() // => 'Event'
 */
enum CreateEventField: string implements MetadataField
{
    use TextField;

    case Name = 'Event';
    case SqlMode = 'sql_mode';
    case TimeZone = 'time_zone';
    case Definition = 'Create Event';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';
}
