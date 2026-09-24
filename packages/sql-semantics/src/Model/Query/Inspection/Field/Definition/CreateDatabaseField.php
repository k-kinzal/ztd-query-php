<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW CREATE DATABASE.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateDatabaseField::Name->label() // => 'Database'
 */
enum CreateDatabaseField: string implements MetadataField
{
    use TextField;

    case Name = 'Database';
    case Definition = 'Create Database';
}
