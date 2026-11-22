<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * MySQL 5.6 `ALTER USER account PASSWORD EXPIRE, …`: a request to mark the passwords of accounts expired.
 *
 * Rule: MYSQL-ALTER-USER-EXPIRE-001. MySQL 5.6 has no other form of ALTER
 * USER; every account is followed by PASSWORD EXPIRE.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/alter-user.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ExpireUserPasswords implements Statement
{
    use Snapshot;

    /**
     * @var list<Account> The accounts in order
     */
    public readonly array $users;

    /**
     * @param list<Account> $users The accounts in order; at least one
     */
    public function __construct(array $users)
    {
        $this->users = Check::listOf($users, Account::class, 'ALTER USER names at least one account.', 1);
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
        $out->keyword('ALTER', 'USER');
        foreach ($this->users as $position => $user) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->node($user)->keyword('PASSWORD', 'EXPIRE');
        }
    }
}
