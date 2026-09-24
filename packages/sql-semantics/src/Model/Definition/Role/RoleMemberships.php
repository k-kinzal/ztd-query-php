<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;

/**
 * Existing roles the new role joins; IN ROLE and IN GROUP are the same request.
 * @visibility public
 * @example Reading the joined roles
 *     $memberships = new \SqlSemantics\Model\Definition\Role\RoleMemberships([\SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser]);
 *     $memberships->roles[0]->value // => 'CURRENT_USER'
 */
final class RoleMemberships
{
    /**
     * @param non-empty-list<NamedRole|SessionRole> $roles Roles the new role joins
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $roles)
    {
        RoleInvariant::roles($roles);
    }
}
