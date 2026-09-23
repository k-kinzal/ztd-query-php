<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Role;

/**
 * Identifies a session role by its lookup rule without resolving its runtime name.
 * @visibility public
 * @example Retaining the role lookup rule
 *     \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser->value // => 'CURRENT_USER'
 */
enum SessionRole: string
{
    case CurrentRole = 'CURRENT_ROLE';
    case CurrentUser = 'CURRENT_USER';
    case SessionUser = 'SESSION_USER';
}
