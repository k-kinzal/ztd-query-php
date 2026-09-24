<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes account identities: quoted names with optional hosts, the authenticated account, and the connecting client.
 * @visibility SqlSemantics
 */
final class Accounts
{
    /**
     * Symbolic accounts keep their keyword spelling; names are written as quoted strings.
     */
    public static function write(AccountName|CurrentAccount|ClientAccount $account): Tree
    {
        return $account instanceof AccountName ? Roles::accounts([$account]) : Build::keyword($account->value);
    }

    /**
     * @param non-empty-list<AccountName|CurrentAccount|ClientAccount> $accounts Ordered identities
     */
    public static function list(array $accounts): Tree
    {
        return Build::separated(array_map(self::write(...), $accounts));
    }

    /**
     * Writes an unsigned count as a plain decimal number.
     */
    public static function count(int $value): Tree
    {
        return new Tree('count', [new Atom('literal', (string) $value)]);
    }
}
