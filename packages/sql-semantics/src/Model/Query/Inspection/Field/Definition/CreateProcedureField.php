<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW CREATE PROCEDURE.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateProcedureField::Name->label() // => 'Procedure'
 */
enum CreateProcedureField: string implements MetadataField
{
    use TextField;

    case Name = 'Procedure';
    case SqlMode = 'sql_mode';
    case Definition = 'Create Procedure';
    case CharacterSetClient = 'character_set_client';
    case CollationConnection = 'collation_connection';
    case DatabaseCollation = 'Database Collation';

    /**
     * The definition is withheld from accounts without the required privilege.
     */
    public function nullability(): Nullability
    {
        return $this === self::Definition ? Nullability::MaybeNull : Nullability::NotNull;
    }
}
