<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

/**
 * Selects every role for a role-level setting change instead of one role.
 * @visibility public
 * @example Inspecting the selection
 *     \SqlSemantics\Model\Definition\Role\AllRoles::All->value // => 'ALL'
 */
enum AllRoles: string
{
    case All = 'ALL';
}
