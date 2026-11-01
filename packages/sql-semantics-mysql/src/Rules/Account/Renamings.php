<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

/**
 * Checks the consistency of the account identities in an ordered rename list.
 *
 * Each pair requires its source identity and excludes its destination identity.
 * Earlier pairs establish the identities available to later pairs. CURRENT_USER
 * can alias any written identity or a distinct one; its original account need
 * not still exist. Both fixed legacy identity and following modern identity are
 * considered. A list is contradictory only if neither interpretation admits
 * consistent account identities. This does not resolve an account catalog.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Renamings
{
    /**
     * @param list<UserRenaming> $renamings The ordered identity constraints
     */
    public function problem(array $renamings): ?string
    {
        $identities = ['unmentioned current account'];
        foreach ($renamings as $renaming) {
            foreach ([$renaming->from, $renaming->to] as $account) {
                if ($account instanceof AccountName) {
                    $identities[] = $this->identity($account, '');
                }
            }
        }
        foreach (array_unique($identities) as $current) {
            if ($this->consistent($renamings, $current, false) || $this->consistent($renamings, $current, true)) {
                return null;
            }
        }
        return 'RENAME USER requires consistent source and destination identities throughout its ordered pairs.';
    }

    /**
     * Propagates identity requirements without choosing any account catalog.
     *
     * @param list<UserRenaming> $renamings The ordered pairs
     * @param string $current The symbolic identity of CURRENT_USER
     * @param bool $follows Whether renaming the current identity changes subsequent CURRENT_USER references
     */
    public function consistent(array $renamings, string $current, bool $follows): bool
    {
        $known = [];
        foreach ($renamings as $renaming) {
            $from = $this->identity($renaming->from, $current);
            $to = $this->identity($renaming->to, $current);
            if ($from === $to || ($known[$from] ?? true) === false || ($known[$to] ?? false) === true) {
                return false;
            }
            $known[$from] = false;
            $known[$to] = true;
            if ($follows && $current === $from) {
                $current = $to;
            }
        }
        return true;
    }

    /**
     * Compares decoded user names exactly and host names without case; an omitted host is %.
     */
    public function identity(Account $account, string $current): string
    {
        return $account instanceof AccountName ? serialize([$account->username(), mb_strtolower($account->hostname() ?? '%', 'UTF-8')]) : $current;
    }
}
