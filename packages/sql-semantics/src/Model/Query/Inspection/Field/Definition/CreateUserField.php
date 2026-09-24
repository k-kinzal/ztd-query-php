<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Definition;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result field of SHOW CREATE USER; the label carries the account.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Definition\CreateUserField::Definition->label() // => 'CREATE USER for '
 */
enum CreateUserField: string implements MetadataField
{
    use TextField;

    case Definition = 'CREATE USER for ';
}
