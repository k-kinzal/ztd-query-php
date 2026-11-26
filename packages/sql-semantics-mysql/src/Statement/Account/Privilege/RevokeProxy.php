<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * `REVOKE [IF EXISTS] PROXY ON account FROM account, … [IGNORE UNKNOWN USER]`: a request to withdraw a proxy grant.
 *
 * Rule: MYSQL-REVOKE-PROXY-001. No relation is named.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RevokeProxy implements Statement
{
    use Snapshot;

    /**
     * @var list<UserSpecification> The proxy accounts
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Account $proxied The proxied account
     * @param list<UserSpecification> $users The proxy accounts; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(public readonly bool $ifExists, public readonly Account $proxied, array $users, public readonly bool $ignoreUnknownUser = false)
    {
        $this->users = Check::listOf($users, UserSpecification::class, 'REVOKE PROXY names at least one account.', 1);
        foreach ($this->users as $user) {
            Check::input($user->granted(), 'A REVOKE account is a named account with at most one authentication method.');
        }
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
