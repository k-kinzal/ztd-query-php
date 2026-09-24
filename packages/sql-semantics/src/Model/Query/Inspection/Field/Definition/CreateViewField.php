<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW CREATE VIEW.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateViewField::Name->label() // => 'View'
 */
enum CreateViewField: string implements MetadataField
{
    use TextField;

    case Name = 'View';
    case Definition = 'Create View';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
}
