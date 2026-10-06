<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SET DEFAULT ROLE {NONE | ALL | role, …} TO account, …` (8.0+): a request to set the default roles of accounts.
 *
 * Mirrors PT_alter_user_default_role as SET DEFAULT ROLE builds it. Rule:
 * MYSQL-SET-DEFAULT-ROLE-001. The accounts are written in the role name
 * syntax, without CURRENT_USER.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-default-role.html. Status: Implemented.
 *
 * @visibility public
 * @example Clearing the default roles of two accounts
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('set default role none to a, b')->toString() // => 'SET DEFAULT ROLE NONE TO a, b'
 */
final class SetDefaultRole implements Statement
{
    use Snapshot;

    /**
     * @var list<AccountName> The accounts in order
     */
    public readonly array $users;

    /**
     * @param RoleSelection $roles The default roles: named roles, ALL or NONE
     * @param list<AccountName> $users The accounts in order; at least one
     */
    public function __construct(public readonly RoleSelection $roles, array $users)
    {
        Check::input($roles->set !== RoleSet::Default && ($roles->set !== RoleSet::All || $roles->roles === []), 'SET DEFAULT ROLE names roles, ALL or NONE.');
        $this->users = Check::listOf($users, AccountName::class, 'SET DEFAULT ROLE names at least one account.', 1);
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
        $out->keyword('SET', 'DEFAULT', 'ROLE')->node($this->roles)->keyword('TO')->list($this->users);
    }
}
