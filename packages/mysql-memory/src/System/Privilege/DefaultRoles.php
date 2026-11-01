<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.default_roles: one for each default role of an account.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class DefaultRoles implements SystemRows
{
    /**
     * Answers a row for each default role.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $accounts = $reading->instance->accounts;
        $rows = [];
        foreach ($accounts->defaults as $key => $roles) {
            $grantee = $accounts->accounts[$key] ?? null;
            if ($grantee === null) {
                continue;
            }
            foreach ($roles as $role) {
                $rows[] = ['HOST' => $grantee->identity->host, 'USER' => $grantee->identity->user, 'DEFAULT_ROLE_HOST' => $role->host, 'DEFAULT_ROLE_USER' => $role->user];
            }
        }
        usort($rows, static fn (array $left, array $right): int => array_values($left) <=> array_values($right));

        return $rows;
    }
}
