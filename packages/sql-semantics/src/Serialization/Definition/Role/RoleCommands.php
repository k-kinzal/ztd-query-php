<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AddGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetAllStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\RenameRoleStatement;
use SqlSemantics\Serialization\Settings;

/**
 * Writes role definitions and alterations; synonyms USER and GROUP collapse to ROLE where equivalent.
 * @visibility SqlSemantics
 */
final class RoleCommands
{
    /**
     * Returns null for statements outside the role and privilege families.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof CreateRoleStatement => new Tree('create-role', [Build::keyword('CREATE ' . $statement->keyword->value), RoleSpecs::role($statement->name), ...array_map(RoleOptions::option(...), $statement->options)]),
            $statement instanceof AlterRoleStatement => new Tree('alter-role', [Build::keyword('ALTER ROLE'), RoleSpecs::role($statement->role), ...RoleOptions::alteration($statement->options)]),
            $statement instanceof RenameRoleStatement => new Tree('rename-role', [Build::keyword('ALTER ROLE'), RoleSpecs::role($statement->role), Build::keyword('RENAME TO'), RoleSpecs::role($statement->newName)]),
            $statement instanceof AddGroupMembersStatement => new Tree('group-members', [Build::keyword('ALTER GROUP'), RoleSpecs::role($statement->group), Build::keyword('ADD USER'), RoleSpecs::roles($statement->members)]),
            $statement instanceof DropGroupMembersStatement => new Tree('group-members', [Build::keyword('ALTER GROUP'), RoleSpecs::role($statement->group), Build::keyword('DROP USER'), RoleSpecs::roles($statement->members)]),
            $statement instanceof DropRolesStatement => new Tree('drop-roles', [Build::keyword('DROP ROLE' . ($statement->ifExists ? ' IF EXISTS' : '')), RoleSpecs::roles($statement->roles)]),
            $statement instanceof AlterRoleSetStatement => new Tree('role-setting', [...self::selection($statement->role, $statement->database), Build::keyword('SET'), Settings::assignment($statement->setting, Dialect::PostgreSql)]),
            $statement instanceof AlterRoleResetStatement => new Tree('role-setting', [...self::selection($statement->role, $statement->database), Build::keyword('RESET'), Build::identifier($statement->setting->name, Dialect::PostgreSql)]),
            $statement instanceof AlterRoleResetAllStatement => new Tree('role-setting', [...self::selection($statement->role, $statement->database), Build::keyword('RESET ALL')]),
            default => PrivilegeCommands::write($statement),
        };
    }

    /**
     * @return list<Tree>
     */
    public static function selection(\SqlSemantics\Model\Configuration\Role\NamedRole|\SqlSemantics\Model\Configuration\Role\SessionRole|\SqlSemantics\Model\Definition\Role\AllRoles $role, ?string $database): array
    {
        return [Build::keyword('ALTER ROLE'), RoleSpecs::role($role), ...($database === null ? [] : [Build::keyword('IN DATABASE'), Build::identifier([$database], Dialect::PostgreSql)])];
    }
}
