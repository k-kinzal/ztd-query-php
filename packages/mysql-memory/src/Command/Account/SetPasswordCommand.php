<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Statement\Operation;

/**
 * Executes SET PASSWORD.
 *
 * The statement commits the open transaction and sets the password of the account FOR names,
 * or of the account of the session, as its plugin stores it, clearing an expired password. An
 * account that does not exist is ER_PASSWORD_NO_MATCH. REPLACE names the current password,
 * which must match for the account of the session and is refused for another account. TO RANDOM
 * answers the generated password (verified on a live 8.4 server). MySQL 5.7 warns that the PASSWORD()
 * form is deprecated (verified on a live 5.7.44 server). MySQL 5.6 takes a string as the hash
 * itself, which must be empty or have 41 characters starting with `*` (ER_PASSWD_LENGTH), and
 * hashes the text of PASSWORD(); OLD_PASSWORD() makes a hash of 16 digits, which it refuses
 * likewise (verified on a live 5.6.51 server).
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
     * Sets the password as MySQL 5.6 does: PASSWORD() hashes the text, and a string is the hash itself, which must be empty or have 41 characters starting with `*`; OLD_PASSWORD() makes a hash of 16 digits, which is refused.
     *
     * @throws SqlError When the hash does not have the form
     */
    public function legacy(Account $account, SetPassword $statement): void
    {
        $text = $statement->password->text->value ?? '';
        $function = $statement->password?->function;
        if ($function === PasswordFunction::Password) {
            $account->hash = (new Credentials(GrammarRelease::MySql5651))->hash('mysql_native_password', $text);
            $account->password = $text;

            return;
        }
        if (($function === PasswordFunction::OldPassword && $text !== '') || ($function === null && $text !== '' && (strlen($text) !== 41 || $text[0] !== '*'))) {
            throw AccountError::PasswordLength->error(41);
        }
        $account->hash = $text;
        $account->password = $text === '' ? '' : null;
    }

    /**
     * Sets the password.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof SetPassword);
        if ($statement->password?->function === PasswordFunction::Password && $session->settings()->release() === GrammarRelease::MySql5744) {
            $for = $statement->user === null ? '' : ' FOR <user>';
            $session->diagnostics->warning(1287, "'SET PASSWORD{$for} = PASSWORD('<plaintext_password>')' is deprecated and will be removed in a future release. Please use SET PASSWORD{$for} = '<plaintext_password>' instead");
        }
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        $names->check([$statement->user]);
        $identity = $statement->user === null ? new Identity($session->user, '%') : $names->identity($statement->user, $session);
        $account = $session->instance->accounts->find($identity);
        if ($account === null) {
            throw AccountError::PasswordNoMatch->error();
        }
        if ($statement->replace !== null) {
            (new AlterUserCommand())->replace($account, $statement->replace->value, $session);
        }
        $credentials = new Credentials($session->settings()->release());
        $password = $statement->password === null ? $credentials->generate() : $statement->password->text->value;
        if ($session->settings()->release() === GrammarRelease::MySql5651) {
            $this->legacy($account, $statement);
        } else {
            $account->hash = $credentials->hash($account->plugin, $password);
            $account->password = $password;
        }
        $account->expired = false;
        if ($statement->password === null) {
            return (new Passwords())->result([[$identity, $password]], $context);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
