<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.columns_priv: one for each column an account holds privileges on.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ColumnsPriv implements SystemRows
{
    /**
     * Answers a row for each column grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach (TablesPriv::tables($account) as [$database, $table, $privileges]) {
                foreach ($privileges->columns as $column => $held) {
                    $rows[] = [
                        'Host' => $account->identity->host,
                        'Db' => $database,
                        'User' => $account->identity->user,
                        'Table_name' => $table,
                        'Column_name' => (string) $column,
                        'Timestamp' => Grantees::time($reading),
                        'Column_priv' => Grantees::set($held, Grantees::COLUMN),
                    ];
                }
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['Host'], $left['Db'], $left['User'], $left['Table_name'], strtolower($left['Column_name'])] <=> [$right['Host'], $right['Db'], $right['User'], $right['Table_name'], strtolower($right['Column_name'])]);

        return $rows;
    }
}
