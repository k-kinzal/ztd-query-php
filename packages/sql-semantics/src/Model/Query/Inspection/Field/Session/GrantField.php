<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result field of SHOW GRANTS; the label carries the account.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\GrantField::Grants->label() // => 'Grants for '
 */
enum GrantField: string implements MetadataField
{
    use TextField;

    case Grants = 'Grants for ';
}
