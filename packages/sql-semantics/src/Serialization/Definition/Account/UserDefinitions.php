<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes CREATE USER from its account definitions and shared clauses.
 * @visibility SqlSemantics
 */
final class UserDefinitions
{
    /**
     * Default roles precede the requirement, limits, policies, and metadata as the MySQL 8 grammar requires.
     */
    public static function write(CreateUsersStatement $statement): Tree
    {
        return new Tree('create-users', [
            Build::keyword('CREATE USER' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')),
            Build::separated(array_map(self::definition(...), $statement->accounts)),
            ...($statement->defaultRoles === [] ? [] : [Build::keyword('DEFAULT ROLE'), Roles::accounts($statement->defaultRoles)]),
            ...AccountClauses::requirement($statement->requirement),
            ...AccountClauses::limits($statement->resourceLimits),
            ...AccountClauses::policies($statement->policies),
            ...AccountClauses::annotation($statement->annotation),
        ]);
    }

    /**
     * Additional factors follow the first with AND; a passwordless plugin adds INITIAL AUTHENTICATION.
     */
    public static function definition(AccountDefinition|InitialAuthenticationDefinition $account): Tree
    {
        $parts = [Accounts::write($account->account)];
        if ($account instanceof InitialAuthenticationDefinition) {
            $parts[] = Build::keyword('IDENTIFIED WITH');
            $parts[] = Identifications::plugin($account->plugin);
            $parts[] = Build::keyword('INITIAL AUTHENTICATION');
            $parts[] = Identifications::write($account->initialAuthentication);
            return new Tree('account-definition', $parts);
        }
        if ($account->identification !== null) {
            $parts[] = Identifications::write($account->identification);
        }
        foreach ($account->additionalFactors as $factor) {
            $parts[] = Build::keyword('AND');
            $parts[] = Identifications::write($factor);
        }
        return new Tree('account-definition', $parts);
    }
}
