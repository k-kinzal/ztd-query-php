<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Role;

/**
 * Selection of roles activated by default for named accounts.
 * @visibility public
 * @example Inspecting a role policy
 *     \SqlSemantics\Model\Configuration\Role\DefaultRolePolicy::All->value // => 'ALL'
 */
enum DefaultRolePolicy: string
{
    case None = 'NONE';
    case All = 'ALL';
}
