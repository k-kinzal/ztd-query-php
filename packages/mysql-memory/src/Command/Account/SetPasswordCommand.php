<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\AccountError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Statement\Operation;

/**
 * Executes SET PASSWORD.
 *
 * The statement commits the open transaction and sets the password of the account FOR names,
 * or of the account of the session, as its plugin stores it, clearing an expired password. An
 * account that does not exist is ER_PASSWORD_NO_MATCH. REPLACE names the current password,
 * which must match for the account of the session and is refused for another account. TO RANDOM
 * answers the generated password (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-password.html.
 *
 * @visibility MySqlMemory
 */
final class SetPasswordCommand implements Command
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
     * Sets the password.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof SetPassword);
        $session->transaction->commit();
        $names = new Names();
        $names->check([$statement->user]);
        $identity = $statement->user === null ? new Identity($session->user, '%') : $names->identity($statement->user, $session);
        $account = $session->instance->accounts->find($identity);
        if ($account === null) {
            throw AccountError::PasswordNoMatch->error();
        }
        if ($statement->replace !== null) {
            (new AlterUserCommand())->replace($account, $statement->replace->value, $session);
        }
        $credentials = new Credentials();
        $password = $statement->password === null ? $credentials->generate() : $statement->password->text->value;
        $account->hash = $credentials->hash($account->plugin, $password);
        $account->password = $password;
        $account->expired = false;
        if ($statement->password === null) {
            return (new Passwords())->result([[$identity, $password]], $context);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
