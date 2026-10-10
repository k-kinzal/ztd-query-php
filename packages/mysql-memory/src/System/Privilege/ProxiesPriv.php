<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.proxies_priv: the accounts each account may proxy, by host, user and proxied account.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ProxiesPriv implements SystemRows
{
    /**
     * Answers a row for each proxy grant.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->instance->accounts->accounts as $account) {
            foreach ($account->grants->proxies as [$proxied, $grantable]) {
                $rows[] = ['Host' => $account->identity->host, 'User' => $account->identity->user, 'Proxied_host' => $proxied->host, 'Proxied_user' => $proxied->user, 'With_grant' => $grantable ? 1 : 0, 'Grantor' => 'root@localhost', 'Timestamp' => Grantees::time($reading)];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left['Host'], $left['User'], $left['Proxied_host'], $left['Proxied_user']] <=> [$right['Host'], $right['User'], $right['Proxied_host'], $right['Proxied_user']]);

        return $rows;
    }
}
