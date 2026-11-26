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
 * `GRANT PROXY ON account TO account, … [WITH GRANT OPTION]`: a request to let accounts act as a proxy for another.
 *
 * Mirrors the TYPE_ENUM_PROXY path of SQLCOM_GRANT. Rule:
 * MYSQL-GRANT-PROXY-001. No relation is named. MySQL 5.x writes the account
 * list of GRANT here, with an optional IDENTIFIED clause per account.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-proxy-privileges,
 * https://dev.mysql.com/doc/refman/8.4/en/proxy-users.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class GrantProxy implements Statement
{
    use Snapshot;

    /**
     * @var list<UserSpecification> The proxy accounts
     */
    public readonly array $grantees;

    /**
     * @param Account $proxied The proxied account
     * @param list<UserSpecification> $grantees The proxy accounts; at least one
     * @param bool $withGrantOption Whether WITH GRANT OPTION is written
     */
    public function __construct(public readonly Account $proxied, array $grantees, public readonly bool $withGrantOption = false)
    {
        $this->grantees = Check::listOf($grantees, UserSpecification::class, 'GRANT PROXY names at least one account.', 1);
        foreach ($this->grantees as $grantee) {
            Check::input($grantee->granted(), 'A GRANT account is a named account with at most one authentication method.');
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
        $out->keyword('GRANT', 'PROXY', 'ON')->node($this->proxied)->keyword('TO')->list($this->grantees);
        if ($this->withGrantOption) {
            $out->keyword('WITH', 'GRANT', 'OPTION');
        }
    }
}
