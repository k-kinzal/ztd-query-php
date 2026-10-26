<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use Closure;
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
 * itself. The recipient plugin determines its format: mysql_native_password accepts 41
 * characters starting with `*`, mysql_old_password accepts 16 characters and warns, and
 * sha256_password requires its own hash format (verified on a live 5.6.51 server).
 * SHA-256 plugins reject a new password exceeding 256 bytes before checking REPLACE;
 * the native plugin accepts longer values (observed on 5.7.44, 8.0.44 and 8.4.7).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-password.html.
 *
 * @visibility MySqlMemory
 */
final class SetPasswordCommand implements Command
{
    /**
     * Defers a legacy SET-list password update until the list applies its assignments.
     *
     */
    public function assignment(\SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment $item, Session $session): Closure
    {
        return function () use ($item, $session): void {
            $names = new Names($session->settings()->release());
            $names->check([$item->user]);
            $identity = $item->user === null ? new Identity($session->user, '%') : $names->identity($item->user, $session);
            $account = $session->instance->accounts->find($identity);
            if ($account === null) {
                throw AccountError::PasswordNoMatch->error();
            }
            $session->transaction->commit();
            $this->legacy($account, new SetPassword($item->user, $item->password), $session);
            $account->expired = false;
            (new \MySqlMemory\Session\Access\PasswordAccess())->changed($account, $session);
        };
    }

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Checks the computed or literal hash against the recipient plugin before assigning it.
     *
     * @throws SqlError When the hash does not have the form
     */
    public function legacy(Account $account, SetPassword $statement, Session $session): void
    {
        $text = $statement->password->text->value ?? '';
        $function = $statement->password?->function;
        $credentials = new Credentials(GrammarRelease::MySql5651);
        $hash = match ($function) {
            PasswordFunction::Password => $credentials->hash('mysql_native_password', $text),
            PasswordFunction::OldPassword => $credentials->hash('mysql_old_password', $text),
            null => $text,
        };
        $length = $account->plugin === 'mysql_old_password' ? 16 : 41;
        if (in_array($account->plugin, ['mysql_old_password', 'mysql_native_password'], true)) {
            if ($hash !== '' && (strlen($hash) !== $length || ($length === 41 && $hash[0] !== '*'))) {
                throw AccountError::PasswordLength->error($length);
            }
        } elseif ($hash !== '' && !str_starts_with($hash, '$5$')) {
            throw AccountError::PasswordFormat->error("The password hash doesn't have the expected format. Check if the correct password algorithm is being used with the PASSWORD() function.");
        }
        if ($length === 16 && $hash !== '') {
            $session->diagnostics->warning(1287, "'pre-4.1 password hash' is deprecated and will be removed in a future release. Please use post-4.1 password hash instead");
        }
        $account->hash = $hash;
        $account->password = $text === '' || $function !== null ? $text : null;
    }

    /**
     * Sets the password.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
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
        if (in_array($account->plugin, ['sha256_password', 'caching_sha2_password'], true) && strlen($statement->password?->text->value ?? '') > 256) {
            throw AccountError::CannotUser->error('SET PASSWORD', $identity->quoted());
        }
        if ($statement->replace !== null) {
            (new AlterUserCommand())->replace($account, $statement->replace->value, $session);
        }
        $credentials = new Credentials($session->settings()->release());
        $password = $statement->password === null ? $credentials->generate((int) $session->variables->read('generated_random_password_length')) : $statement->password->text->value;
        if ($session->settings()->release() === GrammarRelease::MySql5651) {
            $this->legacy($account, $statement, $session);
        } else {
            (new \MySqlMemory\Account\SecondaryPassword())->change($account, $account->plugin, $password === '', $statement->retainCurrent);
            $account->hash = $credentials->hash($account->plugin, $password);
            $account->password = $password;
        }
        $account->expired = false;
        (new \MySqlMemory\Session\Access\PasswordAccess())->changed($account, $session);
        if ($statement->password === null) {
            return (new Passwords())->result([[$identity, $password]], $context);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }
}
