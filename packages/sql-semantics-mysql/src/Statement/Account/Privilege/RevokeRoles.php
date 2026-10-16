<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `REVOKE [IF EXISTS] role, … FROM account, … [IGNORE UNKNOWN USER]` (8.0+): a request to revoke roles.
 *
 * The nonempty role list accepts only GrantedRole operands. Static and
 * column privileges are refused by input lowering before publication.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Revoking a role
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('revoke if exists r from u, v')->toString() // => 'REVOKE IF EXISTS r FROM u, v'
 */
final class RevokeRoles implements Statement, PrivilegeChange
{
    use Snapshot;

    /**
     * @var non-empty-list<GrantedRole> The revoked roles in order
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<Account> The accounts the roles are revoked from
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<GrantedRole> $roles The revoked roles in order; at least one
     * @param list<Account> $users The accounts the roles are revoked from; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(public readonly bool $ifExists, array $roles, array $users, public readonly bool $ignoreUnknownUser = false)
    {
        $this->roles = Check::listOf($roles, GrantedRole::class, 'REVOKE names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'REVOKE names at least one account.', 1);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: role operands have no relation or expression bindings.
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
        $out->list($this->roles)->keyword('FROM')->list($this->users);
        if ($this->ignoreUnknownUser) {
            $out->keyword('IGNORE', 'UNKNOWN', 'USER');
        }
    }
}
