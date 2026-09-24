<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW DATABASES.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\DatabaseField::Name->label() // => 'Database'
 */
enum DatabaseField: string implements MetadataField
{
    use TextField;

    case Name = 'Database';
}
