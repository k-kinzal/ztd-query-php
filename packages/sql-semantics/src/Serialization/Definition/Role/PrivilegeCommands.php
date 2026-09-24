<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Role;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;

/**
 * Writes privilege and membership operations from their typed operands.
 * @visibility SqlSemantics
 */
final class PrivilegeCommands
{
    /**
     * Returns null for statements outside the privilege family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof GrantPrivilegesStatement => new Tree('grant', [Build::keyword('GRANT'), self::privileges($statement->privileges), Build::keyword('ON'), PrivilegeTargets::target($statement->target), Build::keyword('TO'), RoleSpecs::roles($statement->grantees), ...self::grantOption($statement->grantOption), ...self::grantor($statement->grantor)]),
            $statement instanceof RevokePrivilegesStatement => new Tree('revoke', [Build::keyword('REVOKE'), ...self::optionOnly($statement->grantOptionOnly ? 'GRANT' : null), self::privileges($statement->privileges), Build::keyword('ON'), PrivilegeTargets::target($statement->target), Build::keyword('FROM'), RoleSpecs::roles($statement->grantees), ...self::grantor($statement->grantor), ...self::behavior($statement->behavior)]),
            $statement instanceof GrantRolesStatement => new Tree('grant-roles', [Build::keyword('GRANT'), RoleSpecs::roles($statement->roles), Build::keyword('TO'), RoleSpecs::roles($statement->grantees), ...($statement->options === [] ? [] : [Build::keyword('WITH'), Build::separated(array_map(self::option(...), $statement->options))]), ...self::grantor($statement->grantor)]),
            $statement instanceof RevokeRolesStatement => new Tree('revoke-roles', [Build::keyword('REVOKE'), ...self::optionOnly($statement->option?->value), RoleSpecs::roles($statement->roles), Build::keyword('FROM'), RoleSpecs::roles($statement->grantees), ...self::grantor($statement->grantor), ...self::behavior($statement->behavior)]),
            $statement instanceof GrantDefaultPrivilegesStatement => new Tree('default-privileges', [...self::scope($statement->roles, $statement->schemas), Build::keyword('GRANT'), self::privileges($statement->privileges), Build::keyword('ON ' . $statement->target->value . ' TO'), RoleSpecs::roles($statement->grantees), ...self::grantOption($statement->grantOption)]),
            $statement instanceof RevokeDefaultPrivilegesStatement => new Tree('default-privileges', [...self::scope($statement->roles, $statement->schemas), Build::keyword('REVOKE'), ...self::optionOnly($statement->grantOptionOnly ? 'GRANT' : null), self::privileges($statement->privileges), Build::keyword('ON ' . $statement->target->value . ' FROM'), RoleSpecs::roles($statement->grantees), ...self::behavior($statement->behavior)]),
            default => null,
        };
    }

    /**
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges
     */
    public static function privileges(array $privileges): Tree
    {
        return Build::separated(array_map(static fn (ObjectPrivilege|ColumnPrivilege $privilege): Tree => new Tree('privilege', [Build::keyword($privilege->privilege->value), ...($privilege instanceof ColumnPrivilege ? [Build::parentheses(PrivilegeTargets::names($privilege->columns))] : [])]), $privileges));
    }

    /**
     * Writes a membership option with an explicit boolean value.
     */
    public static function option(RoleGrantOption $option): Tree
    {
        return new Tree('membership-option', [Build::keyword($option->attribute->value), Build::keyword($option->granted ? 'TRUE' : 'FALSE')]);
    }

    /**
     * @param list<NamedRole|SessionRole> $roles
     * @param list<string> $schemas
     * @return list<Tree>
     */
    public static function scope(array $roles, array $schemas): array
    {
        return [
            Build::keyword('ALTER DEFAULT PRIVILEGES'),
            ...($roles === [] ? [] : [Build::keyword('FOR ROLE'), RoleSpecs::roles($roles)]),
            ...($schemas === [] ? [] : [Build::keyword('IN SCHEMA'), PrivilegeTargets::names($schemas)]),
        ];
    }

    /**
     * @return list<Tree>
     */
    public static function grantOption(bool $grantOption): array
    {
        return $grantOption ? [Build::keyword('WITH GRANT OPTION')] : [];
    }

    /**
     * @return list<Tree>
     */
    public static function optionOnly(?string $option): array
    {
        return $option === null ? [] : [Build::keyword($option . ' OPTION FOR')];
    }

    /**
     * @return list<Tree>
     */
    public static function grantor(NamedRole|SessionRole|null $grantor): array
    {
        return $grantor === null ? [] : [Build::keyword('GRANTED BY'), RoleSpecs::role($grantor)];
    }

    /**
     * @return list<Tree>
     */
    public static function behavior(DropBehavior $behavior): array
    {
        return $behavior === DropBehavior::Default ? [] : [Build::keyword($behavior->value)];
    }
}
