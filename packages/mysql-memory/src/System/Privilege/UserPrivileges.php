<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.USER_PRIVILEGES: the global privileges of each account.
 *
 * The static privileges come first, in the order SHOW GRANTS writes them, or USAGE for an
 * account that holds none; then the dynamic privileges, in reverse name order. MySQL 5.6 and
 * 5.7 have no dynamic privilege (verified on live 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-user-privileges-table.html.
 *
 * @visibility MySqlMemory
 */
final class UserPrivileges implements SystemRows
{
    /**
     * Answers a row for each global privilege of each account.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::checked($reading) as $account) {
            $grantee = Grantees::grantee($account);
            $global = $account->grants->global;
            $static = array_values(array_filter(Grantees::global(), static fn (string $name): bool => isset($global->names[$name])));
            foreach ($static === [] ? ['USAGE'] : $static as $name) {
                $rows[] = ['GRANTEE' => $grantee, 'TABLE_CATALOG' => 'def', 'PRIVILEGE_TYPE' => $name, 'IS_GRANTABLE' => $global->grantOption && $name !== 'USAGE' ? 'YES' : 'NO'];
            }
            $dynamic = $reading->dictionary() ? $account->grants->dynamic : [];
            krsort($dynamic, SORT_STRING);
            foreach ($dynamic as $name => $grantable) {
                $rows[] = ['GRANTEE' => $grantee, 'TABLE_CATALOG' => 'def', 'PRIVILEGE_TYPE' => $name, 'IS_GRANTABLE' => $grantable ? 'YES' : 'NO'];
            }
        }

        return $rows;
    }
}
