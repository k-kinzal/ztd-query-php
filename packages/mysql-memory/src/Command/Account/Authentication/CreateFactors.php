<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Account\Credentials;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

/**
 * Checks additional authentication methods after the first method has been accepted.
 * An accepted initial or primary credential converts the host before additional plugin
 * lookup and authentication-policy notices. Invalid primary credentials stop earlier.
 * The first omitted plugin reports the authentication-policy warning. Existing accounts
 * reach their existence error without loading additional plugins; new accounts validate
 * the second factor before the third. CURRENT_USER retains only its primary authentication
 * method, including when the account was removed earlier in the same connection.
 * Initial authentication checks a new account's primary plugin before default roles
 * and account attributes; an existing account retains its existence outcome.
 * Verified on live MySQL 8.0, 8.4 and 9.1 servers.
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
        $names = new Names($session->settings()->release());
        $authentication = $user->initial ?? $user->identification;
        $names->ascii($names->identity($user->user, $session), $authentication !== null && $authentication->credential !== Credential::None ? 1 : 0, $session->diagnostics);
        if (!$exists && $user->initial !== null) {
            $credentials = new Credentials($session->settings()->release());
            throw AccountError::PluginOperationUnsupported->error($credentials->plugin($user->identification->plugin->value ?? $credentials->default()));
        }
        if ($user->user instanceof \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser) {
            return;
        }
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
