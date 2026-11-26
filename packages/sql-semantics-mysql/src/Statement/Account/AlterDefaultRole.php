<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * `ALTER USER [IF EXISTS] account DEFAULT ROLE {ALL | NONE | role, …}` (8.0+): a request to set the default roles of an account.
 *
 * Mirrors PT_alter_user_default_role. Rule: MYSQL-ALTER-DEFAULT-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-role. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDefaultRole implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Account $user The account
     * @param RoleSelection $roles The default roles: named roles, ALL or NONE
     */
    public function __construct(public readonly bool $ifExists, public readonly Account $user, public readonly RoleSelection $roles)
    {
        Check::input($roles->set !== RoleSet::Default && ($roles->set !== RoleSet::All || $roles->roles === []), 'DEFAULT ROLE names roles, ALL or NONE.');
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
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->user)->keyword('DEFAULT', 'ROLE')->node($this->roles);
    }
}
