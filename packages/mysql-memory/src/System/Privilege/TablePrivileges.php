<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Catalog;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.TABLE_PRIVILEGES: the privileges of each account on each table, as mysql.tables_priv holds them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-privileges-table.html.
 *
 * @visibility MySqlMemory
 */
final class TablePrivileges implements SystemRows
{
    /**
     * Answers a row for each privilege of each account on each table.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            foreach (TablesPriv::tables($account) as [$database, $table, $privileges]) {
                foreach (Catalog::TABLE as $name) {
                    if (isset($privileges->names[$name])) {
                        $rows[] = ['GRANTEE' => Grantees::grantee($account), 'TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $database, 'TABLE_NAME' => $table, 'PRIVILEGE_TYPE' => $name, 'IS_GRANTABLE' => $privileges->grantOption ? 'YES' : 'NO'];
                    }
                }
            }
        }

        return $rows;
    }
}
