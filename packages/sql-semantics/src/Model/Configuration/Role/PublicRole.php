<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Role;

/**
 * The implicit PUBLIC pseudo-role standing for every role, retained without expanding it.
 * @visibility public
 * @example Inspecting the public grantee
 *     \SqlSemantics\Model\Configuration\Role\PublicRole::Public->value // => 'PUBLIC'
 */
enum PublicRole: string
{
    case Public = 'PUBLIC';
}
