<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;

/**
 * `SET ROLE {DEFAULT | NONE | ALL [EXCEPT role, …] | role, …}` (8.0+): a request to change the active roles of the session.
 *
 * Mirrors PT_set_role. Rule: MYSQL-SET-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-role.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SetRole implements Statement
{
    use Snapshot;

    /**
     * @param RoleSelection $roles The roles to activate
     */
    public function __construct(public readonly RoleSelection $roles)
    {
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
        $out->keyword('SET', 'ROLE')->node($this->roles);
    }
}
