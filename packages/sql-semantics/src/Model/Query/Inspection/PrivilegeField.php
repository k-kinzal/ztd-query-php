<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * Privilege definitions exposed by SHOW PRIVILEGES.
 * @visibility public
 * @example Inspecting a result role
 *     \SqlSemantics\Model\Query\Inspection\PrivilegeField::Name->value // => 'Privilege'
 */
enum PrivilegeField: string
{
    case Name = 'Privilege';
    case Context = 'Context';
    case Description = 'Comment';

    /**
     * Returns the NULL fact declared for this metadata field.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }
}
