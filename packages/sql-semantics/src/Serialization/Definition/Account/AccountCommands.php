<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Account\AccountRename;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Account as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Routes MySQL account administration statements to their writers.
 * @visibility SqlSemantics
 */
final class AccountCommands
{
    /**
     * Returns null for statements outside the account and privilege families.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateRolesStatement => new Tree('create-roles', [Build::keyword('CREATE ROLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Roles::accounts($statement->roles)]),
            $statement instanceof Statement\RenameUsersStatement => new Tree('rename-users', [Build::keyword('RENAME USER'), Build::separated(array_map(static fn (AccountRename $rename): Tree => new Tree('account-rename', [Accounts::write($rename->from), Build::keyword('TO'), Accounts::write($rename->to)]), $statement->renames))]),
            $statement instanceof Statement\AlterDefaultRolePolicyStatement => new Tree('default-role-policy', [Build::keyword('ALTER USER'), Accounts::write($statement->account), Build::keyword('DEFAULT ROLE ' . $statement->policy->value)]),
            $statement instanceof Statement\AlterDefaultRolesStatement => new Tree('default-roles', [Build::keyword('ALTER USER'), Accounts::write($statement->account), Build::keyword('DEFAULT ROLE'), Roles::accounts($statement->roles)]),
            $statement instanceof Statement\InitiateRegistrationStatement => new Tree('factor-registration', [Build::keyword('ALTER USER'), Accounts::write($statement->account), Identifications::factor($statement->factor), Build::keyword('INITIATE REGISTRATION')]),
            $statement instanceof Statement\UnregisterFactorStatement => new Tree('factor-registration', [Build::keyword('ALTER USER'), Accounts::write($statement->account), Identifications::factor($statement->factor), Build::keyword('UNREGISTER')]),
            $statement instanceof Statement\FinishRegistrationStatement => new Tree('factor-registration', [Build::keyword('ALTER USER'), Accounts::write($statement->account), Identifications::factor($statement->factor), Build::keyword('FINISH REGISTRATION SET CHALLENGE_RESPONSE AS'), Expressions::write($statement->challengeResponse)]),
            $statement instanceof Statement\CreateUsersStatement => UserDefinitions::write($statement),
            $statement instanceof Statement\AlterUsersStatement => UserAlterations::write($statement),
            default => PrivilegeCommands::write($statement),
        };
    }
}
