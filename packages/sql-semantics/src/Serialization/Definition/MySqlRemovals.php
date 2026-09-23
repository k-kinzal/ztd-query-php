<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes removal requests directly from names, account identities, and explicit policies.
 * @visibility SqlSemantics
 */
final class MySqlRemovals
{
    /**
     * Returns null for operations outside these named removal families.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\DropUsersStatement) {
            return new Tree('drop-users', [Build::keyword('DROP USER' . ($statement->ifExists ? ' IF EXISTS' : '')), self::accounts($statement->accounts)]);
        }
        if ($statement instanceof Statement\DropRolesStatement) {
            return new Tree('drop-roles', [Build::keyword('DROP ROLE' . ($statement->ifExists ? ' IF EXISTS' : '')), Roles::accounts($statement->roles)]);
        }
        if ($statement instanceof Statement\DropResourceGroupStatement) {
            return new Tree('drop-resource-group', [Build::keyword('DROP RESOURCE GROUP'), Build::identifier([$statement->name], Dialect::MySql), ...($statement->force ? [Build::keyword('FORCE')] : [])]);
        }
        if (!$statement instanceof Statement\DropDatabaseStatement && !$statement instanceof Statement\DropEventStatement && !$statement instanceof Statement\DropServerStatement) {
            return null;
        }
        $operation = match (true) {
            $statement instanceof Statement\DropDatabaseStatement => 'DATABASE',
            $statement instanceof Statement\DropEventStatement => 'EVENT',
            $statement instanceof Statement\DropServerStatement => 'SERVER',
        };
        $name = $statement instanceof Statement\DropEventStatement ? $statement->name->parts : [$statement->name];
        return new Tree('drop-definition', [Build::keyword('DROP ' . $operation . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier($name, Dialect::MySql)]);
    }

    /**
     * @param non-empty-list<AccountName|CurrentAccount> $accounts Ordered account identities
     */
    public static function accounts(array $accounts): Tree
    {
        return Build::separated(array_map(static fn (AccountName|CurrentAccount $account): Tree => $account instanceof CurrentAccount ? Build::keyword($account->value) : Roles::accounts([$account]), $accounts));
    }
}
