<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
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
 * (ER_USER_REGISTRATION_FAILED). UNREGISTER needs the authentication plugin of the factor, which
 * no account has (ER_PLUGIN_IS_NOT_LOADED for the empty name; verified on a live 8.4 server).
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
            throw ErrorCode::NotSupportedYet->error('this registration statement');
        }
        $session->transaction->commit();
        $names = new Names();
        $names->check([$statement->user]);
        if ($statement->step === RegistrationStep::Unregister) {
            throw ErrorCode::PluginIsNotLoaded->error('');
        }
        $current = new Identity($session->user, '%');
        if ($names->identity($statement->user, $session)->key() !== $current->key()) {
            throw ErrorCode::RegistrationNotAllowed->error($current->user, $current->host);
        }
        $factor = (new Literals())->number($statement->factor);

        throw ErrorCode::FactorMissing->error($factor, $factor);
    }
}
