<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Account\Credentials;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

/**
 * Checks additional authentication methods after the first method has been accepted.
 * The first omitted plugin reports the authentication-policy warning. Existing accounts
 * reach their existence error without loading additional plugins; new accounts validate
 * the second factor before the third. Verified on live MySQL 8.0, 8.4 and 9.1 servers.
 *
 * @visibility MySqlMemory
 */
final class CreateFactors
{
    /**
     * Resolves each additional plugin without inventing an installed authentication method.
     *
     * @throws SqlError When a new account requests an unavailable or unsuitable plugin
     */
    public function check(UserSpecification $user, bool $exists, Session $session): void
    {
        foreach ($user->factors as $index => $factor) {
            if ($factor->plugin === null) {
                $session->diagnostics->warning(AccountError::FactorPolicyMismatch, AccountError::FactorPolicyMismatch->message((string) ($index + 2)));
                break;
            }
        }
        if ($exists || $user->factors === []) {
            return;
        }
        $plugin = (new Credentials($session->settings()->release()))->plugin($user->factors[0]->plugin->value ?? '');
        throw AccountError::InvalidFactorPlugin->error($plugin, '2', 'CREATE USER');
    }
}
