<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Privileges;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.tables_priv: one for each table an account holds privileges on, by host, database, user and table.
 *
 * Table_priv lists the privileges on the table, GRANT for GRANT OPTION; Column_priv those
 * held on any of its columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class TablesPriv implements SystemRows
{
    /**
     * Answers a row for each table grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach (self::tables($account) as [$database, $table, $privileges]) {
                $columns = [];
                foreach ($privileges->columns as $held) {
                    $columns += $held;
                }
                $rows[] = [
                    'Host' => $account->identity->host,
                    'Db' => $database,
                    'User' => $account->identity->user,
                    'Table_name' => $table,
                    'Grantor' => 'root@localhost',
                    'Timestamp' => Grantees::time($reading),
                    'Table_priv' => Grantees::set($privileges->names, Grantees::TABLE, $privileges->grantOption),
                    'Column_priv' => Grantees::set($columns, Grantees::COLUMN),
                ];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['Host'], $left['Db'], $left['User'], $left['Table_name']] <=> [$right['Host'], $right['Db'], $right['User'], $right['Table_name']]);

        return $rows;
    }

    /**
     * Answers the tables an account holds privileges on, by database and name.
     *
     * @return list<array{string, string, Privileges}>
     */
    public static function tables(Account $account): array
    {
        $tables = array_values($account->grants->tables);
        usort($tables, static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);

        return $tables;
    }
}
