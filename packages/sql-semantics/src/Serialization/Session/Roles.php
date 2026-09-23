<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\Role as Statement;

/**
 * Writes role selection with required name boundaries, recipients, and policies.
 * @visibility SqlSemantics
 */
final class Roles
{
    /**
     * Writes session activation or account defaults according to the concrete operation.
     */
    public static function write(Statement\SetRolePolicyStatement|Statement\SetExplicitRolesStatement|Statement\SetRolesExceptStatement|Statement\SetDefaultRolePolicyStatement|Statement\SetDefaultRolesStatement $statement): Tree
    {
        if ($statement instanceof Statement\SetDefaultRolePolicyStatement || $statement instanceof Statement\SetDefaultRolesStatement) {
            $selection = $statement instanceof Statement\SetDefaultRolePolicyStatement ? Build::keyword($statement->policy->value) : self::accounts($statement->roles);
            return new Tree('default-roles', [Build::keyword('SET DEFAULT ROLE'), $selection, Build::keyword('TO'), self::accounts($statement->accounts)]);
        }
        $selection = match (true) {
            $statement instanceof Statement\SetRolePolicyStatement => Build::keyword($statement->policy->value),
            $statement instanceof Statement\SetExplicitRolesStatement => self::accounts($statement->roles),
            $statement instanceof Statement\SetRolesExceptStatement => new Tree('role-exclusions', [Build::keyword('ALL EXCEPT'), self::accounts($statement->excludedRoles)]),
        };
        return new Tree('session-roles', [Build::keyword('SET ROLE'), $selection]);
    }

    /**
     * @param non-empty-list<AccountName> $accounts
     */
    public static function accounts(array $accounts): Tree
    {
        return Build::separated(array_map(static fn (AccountName $name): Tree => new Tree('account-name', [
            new Atom('literal', Literal::encode($name->username, Dialect::MySql)[0]),
            ...($name->host === null ? [] : [new Atom('punctuation', '@'), new Atom('literal', Literal::encode($name->host, Dialect::MySql)[0])]),
        ]), $accounts));
    }
}
