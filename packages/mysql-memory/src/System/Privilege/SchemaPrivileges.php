<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Catalog;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.SCHEMA_PRIVILEGES: the privileges of each account on each database, as mysql.db holds them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-schema-privileges-table.html.
 *
 * @visibility MySqlMemory
 */
final class SchemaPrivileges implements SystemRows
{
    /**
     * Answers a row for each privilege of each account on each database.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            $databases = $account->grants->databases;
            ksort($databases, SORT_STRING);
            foreach ($databases as $database => $privileges) {
                foreach (Catalog::DATABASE as $name) {
                    if (isset($privileges->names[$name])) {
                        $rows[] = ['GRANTEE' => Grantees::grantee($account), 'TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $database, 'PRIVILEGE_TYPE' => $name, 'IS_GRANTABLE' => $privileges->grantOption ? 'YES' : 'NO'];
                    }
                }
            }
        }

        return $rows;
    }
}
