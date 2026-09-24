<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;

/**
 * Roles that become members of the new role with the admin option.
 * @visibility public
 * @example Reading the administrators
 *     $admins = new \SqlSemantics\Model\Definition\Role\RoleAdmins([new \SqlSemantics\Model\Configuration\Role\NamedRole('ops')]);
 *     $admins->roles[0]->name // => 'ops'
 */
final class RoleAdmins
{
    /**
     * @param non-empty-list<NamedRole|SessionRole> $roles Roles granted membership with ADMIN OPTION
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $roles)
    {
        RoleInvariant::roles($roles);
    }
}
