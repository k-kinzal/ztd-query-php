<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Account\Account;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Statement;

/**
 * Restricts a session whose password expired until that session changes its own credentials.
 *
 * Already connected sessions have independent flags: an administrator resetting the account
 * does not lift another session's restriction. A successful change by the current session
 * copies the account's resulting expiration state. Verified with two live 8.4 connections.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/password-management.html.
 *
 * @visibility MySqlMemory
 */
final class PasswordAccess
{
    /**
     * Refuses statements other than a password change for this session's account.
     *
     * @throws SqlError When the password must first be changed
     */
    public function check(Statement $statement, Session $session): void
    {
        if (!$session->passwordExpired) {
            return;
        }
        $names = new Names($session->settings()->release());
        if ($statement instanceof SetPassword && ($statement->user === null || $names->identity($statement->user, $session)->text() === $session->variables->definer)) {
            return;
        }
        foreach ($statement instanceof AlterUser ? $statement->users : [] as $user) {
            if ($user instanceof UserSpecification && $user->identification !== null && $names->identity($user->user, $session)->text() === $session->variables->definer) {
                return;
            }
        }
        $verb = $session->settings()->release() === GrammarRelease::MySql5651 ? 'SET PASSWORD' : 'ALTER USER';
        throw AccountError::MustChangePassword->error($verb);
    }

    /**
     * Updates only the session that successfully changed its own account.
     */
    public function changed(Account $account, Session $session): void
    {
        if ($account->identity->text() === $session->variables->definer) {
            $session->passwordExpired = $account->expired;
        }
    }
}
