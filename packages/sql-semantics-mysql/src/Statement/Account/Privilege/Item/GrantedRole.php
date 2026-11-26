<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

/**
 * A role in the list of GRANT or REVOKE (8.0+).
 *
 * Mirrors PT_role_or_dynamic_privilege read as a role and PT_role_at_host.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles.
 *
 * @visibility SqlSemantics
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
