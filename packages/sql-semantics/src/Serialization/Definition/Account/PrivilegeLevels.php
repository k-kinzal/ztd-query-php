<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoleExclusion;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Query\Relations;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes privilege lists, ON levels, recipients, and grantor contexts of MySQL GRANT and REVOKE.
 * @visibility SqlSemantics
 */
final class PrivilegeLevels
{
    /**
     * Tables carry the TABLE keyword and routines their class; wildcard scopes are written bare.
     */
    public static function level(PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): Tree
    {
        return new Tree('privilege-level', match (true) {
            $target instanceof PrivilegeScope => [Build::keyword($target->value)],
            $target instanceof DatabaseScope => [Build::identifier([$target->database], Dialect::MySql), new Atom('punctuation', '.'), new Atom('punctuation', '*')],
            $target instanceof TableReference => [Build::keyword('TABLE'), Relations::target($target, Dialect::MySql)],
            $target instanceof RoutineTarget => [Build::keyword($target->kind->value), Build::identifier($target->name->parts, Dialect::MySql)],
        });
    }

    /**
     * @param non-empty-list<StaticPrivilege|ColumnPrivilege|DynamicPrivilege> $privileges Ordered privileges
     */
    public static function privileges(array $privileges): Tree
    {
        return Build::separated(array_map(static fn (StaticPrivilege|ColumnPrivilege|DynamicPrivilege $privilege): Tree => match (true) {
            $privilege instanceof StaticPrivilege => Build::keyword($privilege->value),
            $privilege instanceof ColumnPrivilege => new Tree('column-privilege', [Build::keyword($privilege->privilege->value), Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::MySql), $privilege->columns)))]),
            $privilege instanceof DynamicPrivilege => Build::identifier([$privilege->name], Dialect::MySql),
        }, $privileges));
    }

    /**
     * Legacy credentialed recipients write their identification after the account.
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Ordered recipients
     */
    public static function grantees(array $grantees): Tree
    {
        return Build::separated(array_map(static fn (AccountName|CurrentAccount|AccountDefinition $grantee): Tree => $grantee instanceof AccountDefinition ? UserDefinitions::definition($grantee) : Accounts::write($grantee), $grantees));
    }

    /**
     * AS account followed by its optional WITH ROLE selection.
     * @return list<Tree>
     */
    public static function grantor(?Grantor $grantor): array
    {
        if ($grantor === null) {
            return [];
        }
        $roles = match (true) {
            $grantor->roles === null => [],
            $grantor->roles instanceof SessionRolePolicy => [Build::keyword('WITH ROLE ' . $grantor->roles->value)],
            $grantor->roles instanceof RoleSelection => [Build::keyword('WITH ROLE'), Roles::accounts($grantor->roles->roles)],
            $grantor->roles instanceof RoleExclusion => [Build::keyword('WITH ROLE ALL EXCEPT'), Roles::accounts($grantor->roles->excludedRoles)],
        };
        return [Build::keyword('AS'), Accounts::write($grantor->account), ...$roles];
    }
}
