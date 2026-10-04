<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `GRANT role, … TO account, … [WITH ADMIN OPTION]` (8.0+): a request to grant roles.
 *
 * Mirrors PT_grant_roles. Rule: MYSQL-GRANT-ROLES-001. The grammar reads any
 * item of the list; an item that is no role (a static privilege, or a
 * dynamic privilege with a column list) is a RoleOrPrivilegeMismatch by
 * MYSQL-PRIVILEGE-CHECKS-001. A dynamic privilege written without columns
 * reads as a role here and is not accepted by the constructor.
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
     * @var list<Grantable> The granted roles in order
     */
    public readonly array $roles;

    /**
     * @var list<Account> The accounts the roles are granted to
     */
    public readonly array $users;

    /**
     * @param list<Grantable> $roles The granted roles in order; at least one
     * @param list<Account> $users The accounts the roles are granted to; at least one
     * @param bool $withAdminOption Whether WITH ADMIN OPTION is written
     */
    public function __construct(array $roles, array $users, public readonly bool $withAdminOption = false)
    {
        $this->roles = Check::listOf($roles, Grantable::class, 'GRANT names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'GRANT names at least one account.', 1);
        foreach ($this->roles as $role) {
            Check::input(!$role instanceof AllPrivileges && !($role instanceof DynamicPrivilege && $role->columns === []), 'A role list holds roles; a name without columns reads as a role.');
        }
    }

    /**
     * Reports the items that are no roles.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->roles($derivation, $this->roles);
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
