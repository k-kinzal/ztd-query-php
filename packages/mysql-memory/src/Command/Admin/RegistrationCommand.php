<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;
use SqlSemantics\Statement\Operation;

/**
 * Executes the registration forms of ALTER USER: INITIATE REGISTRATION, FINISH REGISTRATION and UNREGISTER of a multifactor authentication factor.
 *
 * The statement commits the open transaction. Registration is only allowed in the session of the
 * account registering (ER_INVALID_USER_FOR_REGISTRATION, naming the current account), and the
 * emulated server has no account with a second or third factor
 * (ER_USER_REGISTRATION_FAILED). UNREGISTER of an existing account finds no such factor;
 * a missing account instead fails plugin lookup with an empty name (verified on 8.0–9.1).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-registration.
 *
 * @visibility MySqlMemory
 */
final class RegistrationCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Refuses the registration step, as the server does for an account without that factor.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if (!$statement instanceof AlterRegistration) {
            throw StatementError::NotSupportedYet->error('this registration statement');
        }
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        $names->check([$statement->user]);
        if ($statement->step === RegistrationStep::Unregister) {
            if ($session->instance->accounts->find($names->identity($statement->user, $session)) === null) {
                throw AdministrationError::PluginIsNotLoaded->error('');
            }
            $factor = (new Literals())->number($statement->factor);
            throw AccountError::FactorMissing->error($factor, $factor);
        }
        $current = new Identity($session->user, '%');
        if ($names->identity($statement->user, $session)->key() !== $current->key()) {
            throw AccountError::RegistrationNotAllowed->error($current->user, $current->host);
        }
        $factor = (new Literals())->number($statement->factor);

        throw AccountError::FactorMissing->error($factor, $factor);
    }
}
