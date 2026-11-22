<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * `REVOKE [IF EXISTS] role, … FROM account, … [IGNORE UNKNOWN USER]` (8.0+): a request to revoke roles.
 *
 * Mirrors PT_revoke_roles. Rule: MYSQL-REVOKE-ROLES-001. An item that is no
 * role is a RoleOrPrivilegeMismatch by MYSQL-PRIVILEGE-CHECKS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RevokeRoles implements Statement
{
    use Snapshot;

    /**
     * @var list<Grantable> The revoked roles in order
     */
    public readonly array $roles;

    /**
     * @var list<Account> The accounts the roles are revoked from
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<Grantable> $roles The revoked roles in order; at least one
     * @param list<Account> $users The accounts the roles are revoked from; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(public readonly bool $ifExists, array $roles, array $users, public readonly bool $ignoreUnknownUser = false)
    {
        $this->roles = Check::listOf($roles, Grantable::class, 'REVOKE names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'REVOKE names at least one account.', 1);
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
