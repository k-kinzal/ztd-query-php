<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW STATUS and SHOW VARIABLES.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\VariableField::Name->label() // => 'Variable_name'
 */
enum VariableField: string implements MetadataField
{
    use TextField;

    case Name = 'Variable_name';
    case Value = 'Value';
}
