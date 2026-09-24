<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW CHARACTER SET.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\CharacterSetField::Name->label() // => 'Charset'
 */
enum CharacterSetField: string implements MetadataField
{
    use TextField;

    case Name = 'Charset';
    case Description = 'Description';
    case DefaultCollation = 'Default collation';
    case MaximumLength = 'Maxlen';

    /**
     * The byte length is an integer.
     */
    public function type(): string
    {
        return $this === self::MaximumLength ? 'bigint' : 'varchar';
    }
}
