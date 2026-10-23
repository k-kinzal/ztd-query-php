<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The user-character and host-byte domains of account operands.
 *
 * MySQL 5.6 accepts 16 user characters and 60 host bytes; 5.7 accepts
 * 32 and 60; modern releases accept 32 and 255. An explicit host in 5.6
 * makes both original operands subject to the limit before trimming.
 * Other forms check trimmed operands. Host names cannot contain an at sign.
 * Observed through account and DEFINER statements on isolated native servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountNames
{
    /**
     * Returns the maximum user-character and host-byte counts of a release.
     *
     * @return array{positive-int, positive-int}
     * @throws LogicException When the language profile belongs to another database
     */
    public function limits(GrammarRelease $release): array
    {
        return match ($release) {
            GrammarRelease::MySql5651 => [16, 60],
            GrammarRelease::MySql5744 => [32, 60],
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => [32, 255],
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => throw new LogicException('Account name limits require a MySQL language profile.'),
        };
    }

    /**
     * Reports a refused decoded operand without constructing an account value.
     */
    public function problem(Name $user, ?Name $host, GrammarRelease $release = GrammarRelease::MySql847): ?AccountNameFailure
    {
        $original = $release === GrammarRelease::MySql5651 && $host !== null;
        $username = $original ? $user->value : trim($user->value, " \t\n\r\v\f");
        [$users, $hosts] = $this->limits($release);
        if (mb_strlen($username, 'UTF-8') > $users) {
            return new AccountNameFailure($username, false, $users);
        }
        $hostname = $host === null ? '%' : ($original ? $host->value : trim($host->value, " \t\n\r\v\f"));
        if (strlen($hostname) > $hosts) {
            return new AccountNameFailure($hostname, true, $hosts);
        }

        return str_contains($hostname, '@') ? new AccountNameFailure($hostname, true) : null;
    }
}
