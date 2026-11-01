<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;

/**
 * A role in the list of GRANT or REVOKE (8.0+).
 *
 * Mirrors PT_role_or_dynamic_privilege read as a role and PT_role_at_host.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles.
 *
 * @visibility public
 * @example Reading a granted role
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT reader TO u')->roles[0]->role->user->value // => 'reader'
 */
final class GrantedRole implements Grantable
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param AccountName $role The role
     */
    public function __construct(public readonly AccountName $role, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
