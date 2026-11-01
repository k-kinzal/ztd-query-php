<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

/**
 * Identifies role definitions that no account catalog can satisfy.
 *
 * Conditional definitions can skip existing anonymous or repeated accounts.
 * Unconditional definitions require distinct, nonanonymous account identities.
 * User names compare exactly after trimming; host names compare without case,
 * and an omitted host denotes %. Observed on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoleDefinitions
{
    /**
     * @param list<AccountName> $roles The complete account list in written order
     */
    public function problem(bool $ifNotExists, array $roles): ?string
    {
        if ($ifNotExists) {
            return null;
        }
        $seen = [];
        foreach ($roles as $role) {
            if ($role->username() === '') {
                return 'CREATE ROLE cannot define an anonymous account.';
            }
            $user = $role->username();
            $host = mb_strtolower($role->hostname() ?? '%', 'UTF-8');
            if (isset($seen[$user][$host])) {
                return 'CREATE ROLE cannot unconditionally define the same account twice.';
            }
            $seen[$user][$host] = true;
        }
        return null;
    }
}
