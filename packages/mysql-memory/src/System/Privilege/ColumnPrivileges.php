<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.COLUMN_PRIVILEGES: the privileges of each account on each column, as mysql.columns_priv holds them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-column-privileges-table.html.
 *
 * @visibility MySqlMemory
 */
final class ColumnPrivileges implements SystemRows
{
    /**
     * Answers a row for each privilege of each account on each column.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach (TablesPriv::tables($account) as [$database, $table, $privileges]) {
                $columns = $privileges->columns;
                ksort($columns, SORT_STRING);
                foreach ($columns as $column => $held) {
                    foreach (array_keys(Grantees::COLUMN) as $name) {
                        if (isset($held[$name])) {
                            $rows[] = ['GRANTEE' => Grantees::grantee($account), 'TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $database, 'TABLE_NAME' => $table, 'COLUMN_NAME' => (string) $column, 'PRIVILEGE_TYPE' => $name, 'IS_GRANTABLE' => $privileges->grantOption ? 'YES' : 'NO'];
                        }
                    }
                }
            }
        }

        return $rows;
    }
}
