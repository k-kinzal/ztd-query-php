<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.role_edges: one for each role granted to an account, with whether it may grant the role on.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class RoleEdges implements SystemRows
{
    /**
     * Answers a row for each role grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $accounts = $reading->instance->accounts;
        $rows = [];
        foreach ($accounts->edges as $key => $roles) {
            $grantee = $accounts->accounts[$key] ?? null;
            if ($grantee === null) {
                continue;
            }
            foreach ($roles as [$role, $admin]) {
                $rows[] = ['FROM_HOST' => $role->host, 'FROM_USER' => $role->user, 'TO_HOST' => $grantee->identity->host, 'TO_USER' => $grantee->identity->user, 'WITH_ADMIN_OPTION' => $admin ? 'Y' : 'N'];
            }
        }
        usort($rows, static fn (array $left, array $right): int => array_values($left) <=> array_values($right));

        return $rows;
    }
}
