<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

/**
 * Writes the CREATE USER statement SHOW CREATE USER answers for an account.
 *
 * The statement names the plugin, the authentication string when there is one, the default
 * roles, the TLS requirement, the resource limits when one is set, the password expiration, the
 * lock, the password history, reuse and current-password settings, the failed-login tracking
 * when it is set, and the attributes when there are any. An expired password writes PASSWORD
 * EXPIRE alone. The authentication string and the attributes are quoted with backslash
 * escapes; the TLS values are quoted as they are (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-user.html.
 *
 * @visibility MySqlMemory
 */
final class CreateText
{
    /**
     * Writes the statement of an account.
     *
     * @param list<Identity> $defaults The default roles of the account
     */
    public function statement(Account $account, array $defaults): string
    {
        $text = 'CREATE USER ' . $account->identity->backquoted() . " IDENTIFIED WITH '" . $account->plugin . "'";
        if ($account->hash !== '') {
            $text .= " AS '" . Identity::escape($account->hash) . "'";
        }
        if ($defaults !== []) {
            $names = array_map(static fn (Identity $role): string => $role->backquoted(), $defaults);
            sort($names, SORT_STRING);
            $text .= ' DEFAULT ROLE ' . implode(',', $names);
        }
        $text .= ' REQUIRE ' . $this->tls($account);
        $limits = array_filter($account->limits, static fn (int $limit): bool => $limit !== 0);
        if ($limits !== []) {
            $text .= ' WITH ' . implode(' ', array_map(static fn (string $name, int $limit): string => $name . ' ' . $limit, array_keys($limits), $limits));
        }
        $text .= ' PASSWORD EXPIRE' . match (true) {
            $account->expired => '',
            $account->lifetime === null => ' DEFAULT',
            $account->lifetime === 0 => ' NEVER',
            default => ' INTERVAL ' . $account->lifetime . ' DAY',
        };
        $text .= $account->locked ? ' ACCOUNT LOCK' : ' ACCOUNT UNLOCK';
        $text .= ' PASSWORD HISTORY ' . ($account->history ?? 'DEFAULT');
        $text .= ' PASSWORD REUSE INTERVAL ' . ($account->reuse === null ? 'DEFAULT' : $account->reuse . ' DAY');
        $text .= ' PASSWORD REQUIRE CURRENT' . match ($account->requireCurrent) {
            null => ' DEFAULT',
            true => '',
            false => ' OPTIONAL',
        };
        if ($account->failedAttempts !== 0) {
            $text .= ' FAILED_LOGIN_ATTEMPTS ' . $account->failedAttempts;
        }
        if ($account->lockTime !== 0) {
            $text .= ' PASSWORD_LOCK_TIME ' . ($account->lockTime < 0 ? 'UNBOUNDED' : $account->lockTime);
        }
        if ($account->attributes !== null) {
            $text .= " ATTRIBUTE '" . strtr($account->attributes, ['\\' => '\\\\', "'" => "\\'"]) . "'";
        }

        return $text;
    }

    /**
     * Writes the TLS requirement of an account.
     */
    public function tls(Account $account): string
    {
        return match ($account->tls) {
            TlsKind::None => 'NONE',
            TlsKind::Ssl => 'SSL',
            TlsKind::X509 => 'X509',
            TlsKind::Specified => implode(' ', array_map(static fn (string $name, string $value): string => $name . " '" . $value . "'", array_keys($account->tlsConditions), $account->tlsConditions)),
        };
    }
}
