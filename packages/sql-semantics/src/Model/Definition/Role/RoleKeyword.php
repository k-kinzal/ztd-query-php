<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

/**
 * The definition keyword of a new role; USER implies the LOGIN attribute by default.
 * @visibility public
 * @example Inspecting the keyword
 *     \SqlSemantics\Model\Definition\Role\RoleKeyword::User->value // => 'USER'
 */
enum RoleKeyword: string
{
    case Role = 'ROLE';
    case User = 'USER';
    case Group = 'GROUP';
}
