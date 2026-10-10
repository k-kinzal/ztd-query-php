<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.global_grants: the dynamic privileges of each account, by user, host and privilege.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class GlobalGrants implements SystemRows
{
    /**
     * Answers a row for each dynamic privilege of each account.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->instance->accounts->accounts as $account) {
            foreach ($account->grants->dynamic as $name => $grantable) {
                $rows[] = ['USER' => $account->identity->user, 'HOST' => $account->identity->host, 'PRIV' => $name, 'WITH_GRANT_OPTION' => $grantable ? 'Y' : 'N'];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['USER'], $left['HOST'], $left['PRIV']] <=> [$right['USER'], $right['HOST'], $right['PRIV']]);

        return $rows;
    }
}
