<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * MySQL 5.6 `ALTER USER account PASSWORD EXPIRE, …`: a request to mark the passwords of accounts expired.
 *
 * Rule: MYSQL-ALTER-USER-EXPIRE-001. MySQL 5.6 has no other form of ALTER
 * USER; every account is followed by PASSWORD EXPIRE.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/alter-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Retaining an account outside the legacy name domain
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER USER seventeen_letters PASSWORD EXPIRE');
 *     [$statement->users[0]->user->value, $statement->warnings()[0]->message()] // => ['seventeen_letters', 'The account user exceeds its limit of 16.']
 * @example Expiring two passwords
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51'))->analyze('alter user a password expire, b password expire')->toString() // => 'ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE'
 */
final class ExpireUserPasswords extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Account> The accounts in order
     */
    public readonly array $users;

    /**
     * @param list<Account> $users The accounts in order; at least one
     */
    public function __construct(array $users, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->users = Check::listOf($users, Account::class, 'ALTER USER names at least one account.', 1);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the account name conditions without resolving a runtime catalog.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, $this->users);
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
