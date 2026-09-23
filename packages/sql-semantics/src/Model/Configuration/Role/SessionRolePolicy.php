<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Role;

/**
 * Selection of the current session's active roles.
 * @visibility public
 * @example Inspecting a role policy
 *     \SqlSemantics\Model\Configuration\Role\SessionRolePolicy::All->value // => 'ALL'
 */
enum SessionRolePolicy: string
{
    case None = 'NONE';
    case Default = 'DEFAULT';
    case All = 'ALL';
}
