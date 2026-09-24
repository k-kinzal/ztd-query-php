<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;

/**
 * Roles that become members of the new role; ROLE and USER lists are the same request.
 * @visibility public
 * @example Reading the member selection
 *     $members = new \SqlSemantics\Model\Definition\Role\RoleMembers([new \SqlSemantics\Model\Configuration\Role\NamedRole('alice')]);
 *     $members->roles[0]->name // => 'alice'
 */
final class RoleMembers
{
    /**
     * @param non-empty-list<NamedRole|SessionRole> $roles Roles added as members
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $roles)
    {
        RoleInvariant::roles($roles);
    }
}
