<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Role;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles;

/**
 * Resolves and applies role edges, preserving account lookup and rollback order.
 *
 * @visibility MySqlMemory
 */
final class Grants
{
    /**
     * Checks account name domains before preparation, without requiring accounts.
     */
    public function header(GrantRoles|DeferredGrantRoles $statement, Session $session): void
    {
        $listed = array_map(static fn (GrantedRole $role): AccountName => $role->role, $statement->roles);
        (new Names($session->settings()->release()))->check([...$listed, ...$statement->users]);
    }

    /**
     * Applies the complete role/recipient lists atomically at invocation.
     *
     * @throws SqlError When a recipient, role or edge is refused
     */
    public function apply(GrantRoles|DeferredGrantRoles $statement, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $listed = array_map(static fn (GrantedRole $role): AccountName => $role->role, $statement->roles);
        $names->check([...$listed, ...$statement->users]);
        $accounts = $session->instance->accounts;
        $users = array_map(static fn ($user): Identity => $names->identity($user, $session), $statement->users);
        $roles = array_map(static fn (AccountName $role): Identity => $names->identity($role, $session), $listed);
        $saved = $accounts->copy();
        try {
            foreach ($users as $user) {
                if ($user->user === '') {
                    throw AccountError::AnonymousRoleGrantee->error();
                }
                if ($accounts->find($user) === null) {
                    throw AccountError::UnknownAuthorizationId->error($user->authorization());
                }
                foreach ($roles as $role) {
                    if ($role->user === '') {
                        throw AccountError::FailedRoleGrant->error($role->backquoted(), $user->backquoted());
                    }
                    if ($accounts->find($role) === null) {
                        throw AccountError::UnknownAuthorizationId->error($role->authorization());
                    }
                    if ($role->key() === $user->key() || $accounts->reaches($role, $user)) {
                        throw AccountError::RoleGrantedToItself->error($user->backquoted(), $role->backquoted());
                    }
                    $accounts->grant($role, $user, $statement->withAdminOption);
                }
            }
        } catch (SqlError $failure) {
            $accounts->restore($saved);
            $accounts->reload();
            throw $failure;
        }
    }
}
