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
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Operation;

/**
 * Executes ALTER USER.
 *
 * The statement commits the open transaction. The names and the options are checked as they
 * are parsed, then the plugins and authentication strings; an account that does not exist
 * fails the statement (ER_CANNOT_USER, naming every such account), or with IF EXISTS draws a
 * note. REPLACE names the current password, which must match when the account is the one of
 * the session and is refused for another account. Only the authentication plugins of the
 * server are loaded, and none of them is a second or third factor: ADD of a factor names an
 * invalid plugin, and MODIFY or DROP a factor that does not exist (verified on a live 8.4
 * server). Modern releases roll back a failed change. MySQL 5.7 changes existing accounts
 * even when another named account does not exist.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html.
 * https://dev.mysql.com/doc/refman/8.0/en/atomic-ddl.html.
 *
 * @visibility MySqlMemory
 */
final class AlterUserCommand implements Command
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
     * Changes the accounts.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof ExpireUserPasswords) {
            return $this->expire($statement, $session, $context);
        }
        assert($statement instanceof AlterUser);
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        $names->check($names->users($statement->users));
        $options = new Options($session->settings()->release());
        $options->parsed($operation, $session->text);
        $generated = (new Authentication\AlterRecipients($this, $statement, $session, $options))->apply();
        if ($generated !== []) {
            return (new Passwords())->result($generated, $context);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Executes the MySQL 5.6 form, which expires existing accounts even if another is missing.
     *
     * This release leaves already connected sessions unrestricted, including the acting one.
     * Verified through the public SQL interface on 5.6.51.
     *
     * @throws SqlError When any named account does not exist
     */
    public function expire(ExpireUserPasswords $statement, Session $session, Context $context): Completion
    {
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        $names->check($statement->users);
        $missing = [];
        foreach ($statement->users as $name) {
            $identity = $names->identity($name, $session);
            $account = $session->instance->accounts->find($identity);
            if ($account === null) {
                $missing[] = $identity->quoted();
            } else {
                $account->expired = true;
            }
        }
        if ($missing !== []) {
            throw (new Names($session->settings()->release()))->failure('ALTER USER', implode(',', $missing));
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Refuses an unavailable factor or a built-in plugin used as an additional factor.
     *
     * Omitted authentication plugins warn about the authentication policy before account and
     * factor checks. Existing accounts have only their first factor; ADD 3 needs factor 2 first.
     * A missing account reaches plugin validation, even with IF EXISTS (verified on 8.0–9.1).
     *
     * @throws SqlError When the factor is absent or the plugin cannot supply an additional factor
     */
    public function factors(FactorChange $change, ?Account $account, Session $session): void
    {
        $step = $change->steps[0];
        $factor = $step->factor->value;
        $plugin = $step->identification?->plugin?->value;
        if ($change->action !== FactorAction::Drop && $plugin === null) {
            $session->diagnostics->warning(AccountError::FactorPolicyMismatch, AccountError::FactorPolicyMismatch->message($factor));
        }
        if ($account !== null && ($change->action !== FactorAction::Add || $factor === '3')) {
            $missing = $change->action === FactorAction::Add ? '2' : $factor;
            throw AccountError::FactorMissing->error($missing, $missing);
        }
        $name = (new Credentials($session->settings()->release()))->plugin($plugin ?? '');

        throw AccountError::InvalidFactorPlugin->error($name, $factor, 'ALTER USER');
    }

    /**
     * Changes each account and answers the random passwords generated.
     *
     * @param list<array{Account, \SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration}> $changed
     * @return list<array{Identity, string}>
     *
     * @throws SqlError When a factor does not exist, the current password does not match, or the attributes are not a JSON object
     */
    public function change(array $changed, AlterUser $statement, Session $session, Options $options): array
    {
        $generated = [];
        foreach ($changed as [$account, $user]) {
            if ($user instanceof FactorChange) {
                $factor = $user->steps[0]->factor->value;
                throw AccountError::FactorMissing->error($factor, $factor);
            }
            assert($user instanceof UserSpecification);
            if ($user->replace !== null) {
                $this->replace($account, $user->replace->value, $session);
            }
            if ($user->identification !== null) {
                $password = $options->identify($account, $user->identification, true, (int) $session->variables->read('generated_random_password_length'), $user->retainCurrent);
                if ($password !== null) {
                    $generated[] = [$account->identity, $password];
                }
            }
            if ($user->discardOld) {
                $account->secondary = null;
            }
            $options->apply($account, $statement->tls, $statement->resources, $statement->options, $statement->comment);
        }

        return $generated;
    }

    /**
     * Checks the current password REPLACE names.
     *
     * @throws SqlError When the account is not the one of the session, or the password does not match
     */
    public function replace(Account $account, string $current, Session $session): void
    {
        if ($account->identity->key() !== (new Identity($session->user, '%'))->key()) {
            throw AccountError::CurrentPasswordNotRequired->error();
        }
        if ($account->password !== $current) {
            throw AccountError::IncorrectCurrentPassword->error();
        }
    }
}
