<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `REVOKE [IF EXISTS] PROXY ON account FROM account, … [IGNORE UNKNOWN USER]`: a request to withdraw a proxy grant.
 *
 * Rule: MYSQL-REVOKE-PROXY-001. No relation is named.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Revoking a proxy
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('REVOKE PROXY ON root FROM app')->toString() // => 'REVOKE PROXY ON root FROM app'
 */
final class RevokeProxy extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, PrivilegeChange, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<UserSpecification> The proxy accounts
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Account $proxied The proxied account
     * @param list<UserSpecification> $users The proxy accounts; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(public readonly bool $ifExists, public readonly Account $proxied, array $users, public readonly bool $ignoreUnknownUser = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->users = Check::listOf($users, UserSpecification::class, 'REVOKE PROXY names at least one account.', 1);
        foreach ($this->users as $user) {
            Check::input($user->granted(), 'A REVOKE account is a named account with at most one authentication method.');
            Check::input($user->identification === null, 'A REVOKE account has no authentication clause.');
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the account name conditions without resolving a runtime catalog.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, [$this->proxied, ...$this->users]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REVOKE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->keyword('PROXY', 'ON')->node($this->proxied)->keyword('FROM')->list($this->users);
        if ($this->ignoreUnknownUser) {
            $out->keyword('IGNORE', 'UNKNOWN', 'USER');
        }
    }
}
