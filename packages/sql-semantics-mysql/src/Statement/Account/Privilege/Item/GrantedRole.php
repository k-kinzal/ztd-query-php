<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A role in the list of GRANT or REVOKE (8.0+).
 *
 * Mirrors PT_role_or_dynamic_privilege read as a role and PT_role_at_host.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles.
 *
 * @visibility public
 * @example Reading a granted role
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT reader TO u')->statement->roles[0]->role->user->value // => 'reader'
 */
final class GrantedRole implements Grantable
{
    use Snapshot;

    /**
     * @param AccountName $role The role
     */
    public function __construct(public readonly AccountName $role)
    {
    }

    /**
     * Writes the role.
     */
    public function render(Output $out): void
    {
        $out->node($this->role);
    }
}
