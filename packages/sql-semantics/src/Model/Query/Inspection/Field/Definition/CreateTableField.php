<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW CREATE TABLE.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTableField::Name->label() // => 'Table'
 */
enum CreateTableField: string implements MetadataField
{
    use TextField;

    case Name = 'Table';
    case Definition = 'Create Table';
}
