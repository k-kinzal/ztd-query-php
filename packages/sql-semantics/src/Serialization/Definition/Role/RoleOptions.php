<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Role;

use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes each role option from its typed operands with one canonical keyword spelling.
 * @visibility SqlSemantics
 */
final class RoleOptions
{
    /**
     * Writes the options of an alteration, where a member list uses the USER keyword.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers> $options
     * @return list<Tree>
     */
    public static function alteration(array $options): array
    {
        return array_map(static fn (RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers $option): Tree => self::option($option, false), $options);
    }

    /**
     * ENCRYPTED and IN GROUP synonyms are written in their canonical forms; a member list is ROLE in a definition and USER in an alteration.
     */
    public static function option(RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId $option, bool $defining = true): Tree
    {
        return match (true) {
            $option instanceof RoleAttribute => Build::keyword(($option->granted ? '' : 'NO') . $option->capability->value),
            $option instanceof RolePassword => new Tree('role-password', [Build::keyword('PASSWORD'), Expressions::write($option->secret)]),
            $option instanceof ClearedPassword => Build::keyword('PASSWORD NULL'),
            $option instanceof ConnectionLimit => new Tree('role-connection-limit', [Build::keyword('CONNECTION LIMIT'), new Atom('literal', (string) $option->limit)]),
            $option instanceof RoleValidity => new Tree('role-validity', [Build::keyword('VALID UNTIL'), Expressions::write($option->until)]),
            $option instanceof RoleMembers => new Tree('role-members', [Build::keyword($defining ? 'ROLE' : 'USER'), RoleSpecs::roles($option->roles)]),
            $option instanceof RoleMemberships => new Tree('role-memberships', [Build::keyword('IN ROLE'), RoleSpecs::roles($option->roles)]),
            $option instanceof RoleAdmins => new Tree('role-admins', [Build::keyword('ADMIN'), RoleSpecs::roles($option->roles)]),
            $option instanceof RoleSystemId => new Tree('role-system-id', [Build::keyword('SYSID'), new Atom('literal', (string) $option->id)]),
        };
    }
}
