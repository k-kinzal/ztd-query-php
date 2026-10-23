<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account\Privilege;

use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

/**
 * Distinguishes intrinsic role-grant contradictions from catalog dependencies.
 *
 * An anonymous role, an explicitly anonymous recipient, or an explicit account
 * granted to itself cannot be repaired by establishing account state. CURRENT_USER
 * remains a session dependency. Host names compare without case and omitted hosts
 * denote %. Observed through native GRANT on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoleGrants
{
    /**
     * @param list<GrantedRole> $roles The complete role list
     * @param list<Account> $users The complete recipient list
     */
    public function contradictory(array $roles, array $users): bool
    {
        foreach ($roles as $role) {
            if ($role->role->username() === '') {
                return true;
            }
        }
        foreach ($users as $user) {
            if (!$user instanceof AccountName) {
                continue;
            }
            if ($user->username() === '') {
                return true;
            }
            foreach ($roles as $role) {
                if ($role->role->username() === $user->username() && mb_strtolower($role->role->hostname() ?? '%', 'UTF-8') === mb_strtolower($user->hostname() ?? '%', 'UTF-8')) {
                    return true;
                }
            }
        }
        return false;
    }
}
