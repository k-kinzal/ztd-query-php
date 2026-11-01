<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Catalog;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.db: the privileges of each account on each database, by host, database and user.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class Databases implements SystemRows
{
    /**
     * Answers a row for each database grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach ($account->grants->databases as $database => $privileges) {
                $rows[] = ['Host' => $account->identity->host, 'Db' => $database, 'User' => $account->identity->user] + Grantees::flags($privileges, Catalog::DATABASE);
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['Host'], $left['Db'], $left['User']] <=> [$right['Host'], $right['Db'], $right['User']]);

        return $rows;
    }
}
