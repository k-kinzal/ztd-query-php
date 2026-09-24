<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;

/**
 * Writes role specifications: quoted names for roles, keywords for symbolic selections.
 * @visibility SqlSemantics
 */
final class RoleSpecs
{
    /**
     * Quoting keeps a role literally named CURRENT_USER distinct from the session lookup.
     */
    public static function role(NamedRole|SessionRole|PublicRole|AllRoles $role): Tree
    {
        return $role instanceof NamedRole ? Build::identifier([$role->name], Dialect::PostgreSql) : Build::keyword($role->value);
    }

    /**
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $roles
     */
    public static function roles(array $roles): Tree
    {
        return Build::separated(array_map(self::role(...), $roles));
    }
}
