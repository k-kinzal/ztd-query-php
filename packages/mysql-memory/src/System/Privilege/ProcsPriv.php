<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.procs_priv: one for each stored routine an account holds privileges on.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ProcsPriv implements SystemRows
{
    /**
     * Answers a row for each routine grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach ($account->grants->routines as [$kind, $database, $name, $privileges]) {
                $rows[] = [
                    'Host' => $account->identity->host,
                    'Db' => $database,
                    'User' => $account->identity->user,
                    'Routine_name' => $name,
                    'Routine_type' => $kind,
                    'Grantor' => 'root@localhost',
                    'Proc_priv' => Grantees::set($privileges->names, Grantees::ROUTINE, $privileges->grantOption),
                    'Timestamp' => Grantees::time($reading),
                ];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['Host'], $left['Db'], $left['User'], $left['Routine_name'], $left['Routine_type']] <=> [$right['Host'], $right['Db'], $right['User'], $right['Routine_name'], $right['Routine_type']]);

        return $rows;
    }
}
