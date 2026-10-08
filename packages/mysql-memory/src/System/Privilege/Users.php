<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Account;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

/**
 * The rows of mysql.user: one for each account and role, by host and user, with its global privileges, TLS requirements, limits, authentication and password policy.
 *
 * A role is an account created locked with an expired password. The user attributes hold the
 * metadata ATTRIBUTE and COMMENT set and the failed-login tracking, under metadata and
 * Password_locking. The emulator does not record when a password changed, which it reports as
 * the time the server started.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html.
 *
 * @visibility MySqlMemory
 */
final class Users implements SystemRows
{
    /**
     * Answers a row for each account.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Grantees::stored($reading) as $account) {
            $rows[] = ['Host' => $account->identity->host, 'User' => $account->identity->user] + Grantees::flags($account->grants->global, Grantees::global()) + [
                'ssl_type' => match ($account->tls) {
                    TlsKind::None => '',
                    TlsKind::Ssl => 'ANY',
                    TlsKind::X509 => 'X509',
                    TlsKind::Specified => 'SPECIFIED',
                },
                'ssl_cipher' => $account->tlsConditions['CIPHER'] ?? '',
                'x509_issuer' => $account->tlsConditions['ISSUER'] ?? '',
                'x509_subject' => $account->tlsConditions['SUBJECT'] ?? '',
                'max_questions' => $account->limits['MAX_QUERIES_PER_HOUR'] ?? 0,
                'max_updates' => $account->limits['MAX_UPDATES_PER_HOUR'] ?? 0,
                'max_connections' => $account->limits['MAX_CONNECTIONS_PER_HOUR'] ?? 0,
                'max_user_connections' => $account->limits['MAX_USER_CONNECTIONS'] ?? 0,
                'plugin' => $account->plugin,
                'authentication_string' => $account->hash,
                'password_expired' => $account->expired ? 'Y' : 'N',
                'password_last_changed' => Grantees::time($reading),
                'password_lifetime' => $account->lifetime,
                'account_locked' => $account->locked ? 'Y' : 'N',
                'Password_reuse_history' => $account->history,
                'Password_reuse_time' => $account->reuse,
                'Password_require_current' => $account->requireCurrent === null ? null : ($account->requireCurrent ? 'Y' : 'N'),
                'User_attributes' => self::attributes($account),
            ];
        }

        return $rows;
    }

    /**
     * Answers the user attributes of an account as the server writes the JSON object, or null for none.
     */
    public static function attributes(Account $account): ?string
    {
        $members = [];
        if ($account->attributes !== null) {
            $members[] = '"metadata": ' . $account->attributes;
        }
        if ($account->failedAttempts !== 0 || $account->lockTime !== 0) {
            $members[] = '"Password_locking": {"failed_login_attempts": ' . $account->failedAttempts . ', "password_lock_time_days": ' . $account->lockTime . '}';
        }

        return $members === [] ? null : '{' . implode(', ', $members) . '}';
    }
}
