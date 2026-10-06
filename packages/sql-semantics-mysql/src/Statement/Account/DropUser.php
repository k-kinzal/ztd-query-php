<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP USER [IF EXISTS] account, …`: a request to remove accounts and their privileges.
 *
 * Mirrors SQLCOM_DROP_USER. Rule: MYSQL-DROP-USER-001. Accounts are not
 * declared in a context, so the statement has no resolution facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Dropping accounts
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("drop user if exists a, 'b'@'h'")->toString() // => 'DROP USER IF EXISTS a, b@h'
 */
final class DropUser implements Statement
{
    use Snapshot;

    /**
     * @var list<Account> The accounts in order
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<Account> $users The accounts in order; at least one
     */
    public function __construct(public readonly bool $ifExists, array $users)
    {
        $this->users = Check::listOf($users, Account::class, 'DROP USER names at least one account.', 1);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'USER');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->users);
    }
}
