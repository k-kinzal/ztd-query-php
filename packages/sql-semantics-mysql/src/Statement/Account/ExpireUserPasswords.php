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
 * MySQL 5.6 `ALTER USER account PASSWORD EXPIRE, …`: a request to mark the passwords of accounts expired.
 *
 * Rule: MYSQL-ALTER-USER-EXPIRE-001. MySQL 5.6 has no other form of ALTER
 * USER; every account is followed by PASSWORD EXPIRE.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/alter-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Expiring two passwords
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51'))->analyze('alter user a password expire, b password expire')->toString() // => 'ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE'
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
