<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW TABLES; the name label carries the listed database.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\TableField::Name->label() // => 'Tables_in_'
 */
enum TableField: string implements MetadataField
{
    use TextField;

    case Name = 'Tables_in_';
    case Type = 'Table_type';
}
