<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;

/**
 * Applies the acting account's exemption from authentication-policy restrictions.
 *
 * Dynamic privileges take effect immediately, including revocation within an
 * existing connection. Active roles contribute their transitive privileges.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_authentication_policy.
 *
 * @visibility MySqlMemory
 */
final class FactorPolicy
{
    /**
     * Reports a policy mismatch as a warning only for an exempt administrator.
     *
     * @throws SqlError When the acting account has no policy exemption
     */
    public function mismatch(string $factor, Session $session): void
    {
        $accounts = $session->instance->accounts;
        $grants = $accounts->privileges($session->variables->definer, $session->variables->roles);
        if (!isset($grants->dynamic['AUTHENTICATION_POLICY_ADMIN'])) {
            throw AccountError::FactorPolicyMismatch->error($factor);
        }
        $session->diagnostics->warning(AccountError::FactorPolicyMismatch, AccountError::FactorPolicyMismatch->message($factor));
    }
}
