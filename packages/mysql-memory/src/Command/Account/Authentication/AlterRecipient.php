<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\AlterUserCommand;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Account\Options;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Access\PasswordAccess;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

/**
 * Checks one ALTER USER occurrence against the current account catalog before changing it.
 * Missing accounts still validate their authentication plugin before the existence note.
 * Batch atomicity and final errors belong to AlterRecipients.
 *
 * @visibility MySqlMemory
 */
final class AlterRecipient
{
    /**
     * Returns the identity, existing account and any generated password for this occurrence.
     *
     * @return array{Identity, Account|null, list<array{Identity, string}>}
     * @throws SqlError When its authentication or options are refused
     */
    public function apply(AlterUser $statement, UserAlteration $user, Session $session, Options $options): array
    {
        $names = new Names($session->settings()->release());
        $identity = $names->identity($names->users([$user])[0], $session);
        $account = $session->instance->accounts->find($identity);
        $command = new AlterUserCommand();
        if ($user instanceof UserSpecification) {
            $options->check($user->identification, $account->plugin ?? (new Credentials($session->settings()->release()))->default());
        }
        if ($user instanceof FactorChange) {
            $command->factors($user, $account, $session);
        }
        if ($account === null) {
            if ($statement->ifExists) {
                $session->diagnostics->note(AccountError::UserDoesNotExist, $names->existence($identity, false));
            }
            return [$identity, null, []];
        }
        $generated = $command->change([[$account, $user]], $statement, $session, $options);
        (new PasswordAccess())->changed($account, $session);

        return [$identity, $account, $generated];
    }
}
