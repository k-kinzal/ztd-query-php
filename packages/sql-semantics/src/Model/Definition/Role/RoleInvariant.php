<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enforces PostgreSQL role operand domains shared by definitions, alterations, and settings.
 * @visibility SqlSemantics
 */
final class RoleInvariant
{
    /**
     * Role operations exist only in PostgreSQL.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Role operations require PostgreSQL.');
        }
    }

    /**
     * @param list<NamedRole|SessionRole> $roles Role selection
     * @throws InvalidStructure
     */
    public static function roles(array $roles): void
    {
        Collections::alternatives(Collections::nonEmpty($roles), [NamedRole::class, SessionRole::class]);
    }

    /**
     * Memberships, administrators, and system identifiers are accepted only by a definition.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId> $options Ordered role options
     * @throws InvalidStructure
     */
    public static function options(Origin $origin, array $options, bool $creating): void
    {
        self::dialect($origin);
        $alteration = [RoleAttribute::class, RolePassword::class, ClearedPassword::class, ConnectionLimit::class, RoleValidity::class, RoleMembers::class];
        Collections::alternatives($options, $creating ? [...$alteration, RoleMemberships::class, RoleAdmins::class, RoleSystemId::class] : $alteration);
        if (self::conflicting($options)) {
            throw new InvalidStructure('A role definition cannot repeat an option or request both forms of one attribute.');
        }
    }

    /**
     * Reports whether two options address the same role property.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId> $options Ordered role options
     */
    public static function conflicting(array $options): bool
    {
        $seen = [];
        foreach ($options as $option) {
            $property = self::property($option);
            if (isset($seen[$property])) {
                return true;
            }
            $seen[$property] = true;
        }
        return false;
    }

    /**
     * Names the role property an option addresses; both password forms share one property.
     */
    public static function property(RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId $option): string
    {
        return match (true) {
            $option instanceof RoleAttribute => 'attribute:' . $option->capability->value,
            $option instanceof RolePassword, $option instanceof ClearedPassword => 'password',
            $option instanceof ConnectionLimit => 'connection-limit',
            $option instanceof RoleValidity => 'valid-until',
            $option instanceof RoleMembers => 'members',
            $option instanceof RoleMemberships => 'memberships',
            $option instanceof RoleAdmins => 'admins',
            $option instanceof RoleSystemId => 'sysid',
        };
    }

    /**
     * A database qualifier for a role setting requires a name when present.
     * @throws InvalidStructure
     */
    public static function database(Origin $origin, ?string $database): void
    {
        self::dialect($origin);
        if ($database === '') {
            throw new InvalidStructure('A role setting database qualifier requires a nonempty name.');
        }
    }
}
