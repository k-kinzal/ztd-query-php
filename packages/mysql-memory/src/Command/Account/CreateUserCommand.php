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
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE USER and CREATE ROLE.
 *
 * The statement commits the open transaction. The names are checked as they are parsed, then
 * the options, then the plugins and authentication strings; an account that exists fails the
 * statement (ER_CANNOT_USER, naming every such account), or with IF NOT EXISTS draws a note
 * and is left as it is. A default role must exist. Nothing is created when the statement fails.
 * A role is created locked, with an expired password. A random password is answered as a row of
 * the user, the host, the password and the factor (verified on a live 8.4 server). MySQL 5.6 and
 * 5.7 create accounts with mysql_native_password and one at a time (see legacy()), and 5.7 warns
 * that IDENTIFIED BY PASSWORD is deprecated (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-role.html.
 *
 * @visibility MySqlMemory
 */
final class CreateUserCommand implements Command
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
     * Creates the accounts.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        if ($statement instanceof CreateRole) {
            return (new Role\Definitions())->execute($statement, $session, $context);
        }
        assert($statement instanceof CreateUser);
        $names->check([...$names->users($statement->users), ...$statement->defaultRoles]);
        $release = $session->settings()->release();
        $options = new Options($release);
        $options->parsed($operation, $session->text);
        $plugin = (new Credentials($release))->default();
        $this->deprecated($statement, $session);
        if ((new Credentials($release))->legacy()) {
            return $this->legacy($statement, $session, $context, $plugin);
        }
        $created = $this->absent($statement, $names, $session, $context);
        $defaults = $created === [] ? [] : $this->defaults($statement, $session);
        $accounts = $session->instance->accounts;
        $saved = $accounts->copy();
        try {
            $generated = $this->create($created, $defaults, $statement, $session, $context);
        } catch (SqlError $error) {
            $accounts->restore($saved);
            $accounts->reload();
            throw $error;
        }
        if ($generated !== []) {
            return (new Passwords())->result($generated, $context);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Answers the accounts of CREATE USER that do not exist yet, each named once.
     *
     * Initial authentication is checked even for existing accounts under IF NOT EXISTS.
     * Existing accounts validate explicit credentials before drawing notes;
     * a plugin named alone is skipped under IF NOT EXISTS.
     * Other occurrences validate their credentials before checking existence. All occurrences
     * contribute diagnostics in order; credential failures precede the final existence error.
     * A new recipient's grant-table conversions are observed even if another recipient
     * later rolls back the batch. Verified on MySQL 8.0, 8.4 and 9.1.
     * Default roles are resolved only for new recipients. After a role refusal,
     * later new recipients cannot receive roles in that failed account batch.
     *
     * @return list<array{Identity, UserSpecification}>
     *
     * @throws SqlError When an account exists and the statement has no IF NOT EXISTS
     */
    public function absent(CreateUser $statement, Names $names, Session $session, Context $context): array
    {
        $failed = [];
        $created = [];
        $first = null;
        $rolesRefused = false;
        $options = new Options($session->settings()->release());
        $plugin = (new Credentials($session->settings()->release()))->default();
        foreach ($statement->users as $user) {
            $identity = $names->identity($user->user, $session);
            (new Authentication\DefinerReferences())->record($identity, $session, $context);
            $exists = $session->instance->accounts->find($identity) !== null || isset($created[$identity->key()]);
            try {
                $options->check($user->initial, $plugin);
                if ($exists && $statement->ifNotExists) {
                    $options->check($user->identification?->credential !== Credential::None ? $user->identification : null, $plugin);
                    $names->ascii($identity, 1, $context->diagnostics);
                    $context->diagnostics->note(AccountError::UserAlreadyExists, $names->existence($identity, true));
                    continue;
                }
                $options->check($user->initial === null ? $user->identification : null, $plugin);
                (new Authentication\CreateFactors())->check($user, $exists, $session);
                $exists = $exists || $session->instance->accounts->stored($identity->stored()) !== null || array_filter($created, static fn (array $previous): bool => $previous[0]->stored()->key() === $identity->stored()->key()) !== [];
                if (!$exists) {
                    $this->recipient($statement, $identity, $rolesRefused, $session, $user->initial !== null);
                }
            } catch (SqlError $error) {
                $rolesRefused = $rolesRefused || $error->error === AccountError::UserDoesNotExist;
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
                if ($error->error === AccountError::InvalidUserAttributeJson) {
                    break;
                }
                continue;
            }
            if ($exists) {
                $names->ascii($identity, 1, $context->diagnostics);
                if ($statement->ifNotExists) {
                    $context->diagnostics->note(AccountError::UserAlreadyExists, $names->existence($identity, true));
                    continue;
                }
                $failed[] = $identity;
                continue;
            }
            $created[$identity->key()] = [$identity, $user];
        }
        $this->failures($first, $failed, $session);
        return array_values($created);
    }

    /**
     * Reports the account batch's first refusal and reloads persisted identities after rollback.
     *
     * @param list<Identity> $failed Accounts that already exist
     * @throws SqlError When credentials or an existing recipient refuse the batch
     */
    public function failures(?SqlError $first, array $failed, Session $session): void
    {
        if ($first !== null) {
            $session->instance->accounts->reload();
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }
        if ($failed !== []) {
            $session->instance->accounts->reload();
            throw (new Names($session->settings()->release()))->failure('CREATE USER', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }

    }

    /**
     * Checks a new recipient's roles and shared attributes after its credentials.
     *
     * @throws SqlError When roles or the JSON attributes are invalid
     */
    public function recipient(CreateUser $statement, Identity $identity, bool $rolesRefused, Session $session, bool $initial = false): void
    {
        if ($rolesRefused) {
            throw AccountError::UserDoesNotExist->error($identity->backquoted());
        }
        $this->defaults($statement, $session);
        if ($statement->comment?->kind === \SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Attribute) {
            (new \MySqlMemory\Account\Attributes())->merge(null, $statement->comment->text->value);
        }
        if (!$initial) {
            (new Names($session->settings()->release()))->ascii($identity, 8, $session->diagnostics);
        }
    }

    /**
     * Records the warning MySQL 5.7 raises for each account IDENTIFIED BY PASSWORD names (verified on a live 5.7.44 server).
     */
    public function deprecated(CreateUser $statement, Session $session): void
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5744) {
            return;
        }
        foreach ($statement->users as $user) {
            if ($user->identification?->credential === Credential::PasswordHash) {
                $session->diagnostics->warning(1287, "'IDENTIFIED BY PASSWORD' is deprecated and will be removed in a future release. Please use IDENTIFIED WITH <plugin> AS <hash> instead");
            }
        }
    }

    /**
     * Creates accounts one after another in MySQL 5.6 and 5.7, preserving successful changes.
     * Credential failures are followed by ER_CANNOT_USER, which lists credential and existence
     * failures in 5.6, and existence failures alone in 5.7 (verified on isolated servers).
     *
     * @param string $plugin The plugin an account takes when the statement names none
     *
     * @throws SqlError When an account is refused or exists
     */
    public function legacy(CreateUser $statement, Session $session, Context $context, string $plugin): Reply
    {
        $release = $session->settings()->release();
        $names = new Names($release);
        $options = new Options($release);
        $accounts = $session->instance->accounts;
        $refusal = null;
        $refused = [];
        $failed = [];
        foreach ($statement->users as $user) {
            $identity = $names->identity($user->user, $session);
            try {
                $options->check($user->identification, $plugin);
            } catch (SqlError $error) {
                $refusal ??= $error;
                $refused[] = $identity;
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                continue;
            }
            if ($accounts->find($identity) !== null) {
                if ($statement->ifNotExists) {
                    $context->diagnostics->note(AccountError::UserAlreadyExists, $names->existence($identity, true));
                } else {
                    $failed[] = $identity;
                }
                continue;
            }
            $this->create([[$identity, $user]], [], $statement, $session, $context);
        }
        $quoted = static fn (Identity $identity): string => $identity->quoted();
        if ($refusal !== null) {
            $listed = implode(',', array_map($quoted, $release === GrammarRelease::MySql5651 ? [...$refused, ...$failed] : $failed));

            throw new SqlError($refusal->error, $refusal->getMessage(), $refusal, [[AccountError::CannotUser->value, AccountError::CannotUser->message('CREATE USER', $listed)]], null, null, true);
        }
        if ($failed !== []) {
            throw (new Names($session->settings()->release()))->failure('CREATE USER', implode(',', array_map($quoted, $failed)));
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Answers the default roles of CREATE USER, each of which must exist.
     *
     * @return list<Identity>
     *
     * @throws SqlError When a role does not exist
     */
    public function defaults(CreateUser $statement, Session $session): array
    {
        $names = new Names($session->settings()->release());
        $defaults = [];
        foreach ($statement->defaultRoles as $role) {
            $identity = $names->identity($role, $session);
            if ($session->instance->accounts->find($identity) === null) {
                throw AccountError::UserDoesNotExist->error($identity->backquoted());
            }
            $defaults[] = $identity;
        }

        return $defaults;
    }

    /**
     * Creates accounts and answers the random passwords generated.
     *
     * @param list<array{Identity, UserSpecification}> $created
     * @param list<Identity> $defaults The default roles, granted to each account
     * @return list<array{Identity, string}>
     *
     * @throws SqlError When an authentication string has not its form, or the attributes are not a JSON object
     */
    public function create(array $created, array $defaults, CreateUser $statement, Session $session, Context $context): array
    {
        $accounts = $session->instance->accounts;
        $options = new Options($session->settings()->release());
        $generated = [];
        foreach ($created as [$identity, $user]) {
            if ($user->initial !== null) {
                $credentials = new Credentials($session->settings()->release());
                throw AccountError::PluginOperationUnsupported->error($credentials->plugin($user->identification->plugin->value ?? $credentials->default()));
            }
            $account = new Account($identity, (new Credentials($session->settings()->release()))->default());
            $password = $user->identification === null ? null : $options->identify($account, $user->identification, false, (int) $session->variables->read('generated_random_password_length'));
            $options->apply($account, $statement->tls, $statement->resources, $statement->options, $statement->comment);
            $accounts->add($account);
            foreach ($defaults as $role) {
                $accounts->grant($role, $identity, false);
                $accounts->defaults[$identity->key()][$role->key()] = $role;
            }
            if ($password !== null) {
                $generated[] = [$identity, $password];
            }
        }

        return $generated;
    }

    /**
     * Creates roles.
     *
     * @param list<Identity> $roles
     *
     * @throws SqlError When a role exists or is anonymous
     */
    public function roles(array $roles, bool $ifNotExists, Session $session, Context $context): Reply
    {
        return (new Role\Definitions())->create($roles, $ifNotExists, $session, $context);
    }
}
