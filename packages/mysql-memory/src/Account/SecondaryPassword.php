<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;

/**
 * Applies dual-password state transitions before replacing a primary credential.
 *
 * Retaining replaces any older secondary password with the current primary. An ordinary
 * password change preserves the secondary, except when the new password is empty or the
 * authentication plugin changes. Retention rejects an empty current or new password and
 * a changed plugin (verified through SQL on 8.4.7).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/password-management.html#dual-passwords.
 *
 * @visibility MySqlMemory
 */
final class SecondaryPassword
{
    /**
     * Checks a retained password's plugin identity before plugin lookup or hash validation.
     *
     * @param string $plugin The proposed plugin name, without requiring it to be loaded
     * @throws SqlError When retention would change the existing plugin
     */
    public function check(Account $account, string $plugin, bool $retain): void
    {
        if ($retain && strcasecmp($plugin, $account->plugin) !== 0) {
            throw AccountError::RetainChangesPlugin->error($account->identity->user, $account->identity->host);
        }
    }

    /**
     * Updates the secondary credential after validation and before changing the primary.
     *
     * @param string $plugin The new primary authentication plugin
     * @param bool $empty Whether the new primary credential is empty
     * @param bool $retain Whether RETAIN CURRENT PASSWORD is written
     * @throws SqlError When the requested retention cannot be performed
     */
    public function change(Account $account, string $plugin, bool $empty, bool $retain): void
    {
        $this->check($account, $plugin, $retain);
        if ($retain) {
            $error = match (true) {
                $account->hash === '' => AccountError::RetainEmptyPassword,
                $empty => AccountError::RetainWithEmptyPassword,
                default => null,
            };
            if ($error !== null) {
                throw $error->error($account->identity->user, $account->identity->host);
            }
            $account->secondary = [$account->hash, $account->password];
        } elseif ($empty || $plugin !== $account->plugin) {
            $account->secondary = null;
        }
    }
}
