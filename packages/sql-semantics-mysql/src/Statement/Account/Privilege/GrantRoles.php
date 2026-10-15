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
 * `GRANT role, … TO account, … [WITH ADMIN OPTION]` (8.0+): a request to grant roles.
 *
 * The nonempty role list accepts only GrantedRole operands. Static and
 * column privileges are refused by input lowering before publication.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles. Status: Implemented.
 *
 * @visibility public
 * @example Granting roles with the admin option
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("grant r1, 'r2'@'%' to u with admin option")->toString() // => 'GRANT r1, r2@`%` TO u WITH ADMIN OPTION'
 */
final class GrantRoles implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<GrantedRole> The granted roles in order
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<Account> The accounts the roles are granted to
     */
    public readonly array $users;

    /**
     * @param list<GrantedRole> $roles The granted roles in order; at least one
     * @param list<Account> $users The accounts the roles are granted to; at least one
     * @param bool $withAdminOption Whether WITH ADMIN OPTION is written
     */
    public function __construct(array $roles, array $users, public readonly bool $withAdminOption = false)
    {
        $this->roles = Check::listOf($roles, GrantedRole::class, 'GRANT names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'GRANT names at least one account.', 1);
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
        $out->keyword('GRANT')->list($this->roles)->keyword('TO')->list($this->users);
        if ($this->withAdminOption) {
            $out->keyword('WITH', 'ADMIN', 'OPTION');
        }
    }
}
