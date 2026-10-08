<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
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
 * the user, the host, the password and the factor (verified on a live 8.4 server).
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        if ($statement instanceof CreateRole) {
            $names->check($statement->roles);
            $roles = array_map(static fn ($role): Identity => $names->identity($role, $session), $statement->roles);

            return $this->roles($roles, $statement->ifNotExists, $session, $context);
        }
        assert($statement instanceof CreateUser);
        $names->check([...$names->users($statement->users), ...$statement->defaultRoles]);
        $options = new Options();
        $options->parsed($operation, $session->text);
        foreach ($statement->users as $user) {
            $options->check($user->identification, 'caching_sha2_password');
            $options->check($user->initial, 'caching_sha2_password');
        }
        $accounts = $session->instance->accounts;
        $failed = [];
        $created = [];
        foreach ($statement->users as $user) {
            $identity = $names->identity($user->user, $session);
            if ($accounts->find($identity) !== null || isset($created[$identity->key()])) {
                $failed[] = $identity;
                continue;
            }
            $created[$identity->key()] = [$identity, $user];
        }
        if ($failed !== [] && !$statement->ifNotExists) {
            throw AccountError::CannotUser->error('CREATE USER', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }
        foreach ($failed as $identity) {
            $context->diagnostics->note(AccountError::UserAlreadyExists, AccountError::UserAlreadyExists->message($identity->quoted()));
        }
        $defaults = $this->defaults($statement, $session);
        $saved = $accounts->copy();
        try {
            $generated = $this->create(array_values($created), $defaults, $statement, $session, $context);
        } catch (SqlError $error) {
            $accounts->restore($saved);
            throw $error;
        }
        if ($generated !== []) {
            return (new Passwords())->result($generated, $context);
        }

        return new Completion(0, 0, $context->diagnostics->count());
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
        $options = new Options();
        $generated = [];
        foreach ($created as [$identity, $user]) {
            $account = new Account($identity);
            $password = $user->identification === null ? null : $options->identify($account, $user->identification, false);
            $password = $user->initial === null ? $password : $options->identify($account, $user->initial, false);
            $options->apply($account, $statement->tls, $statement->resources, $statement->options, $statement->comment);
            $accounts->add($account);
            (new Names())->ascii($identity, 8, $context->diagnostics);
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
        $accounts = $session->instance->accounts;
        $failed = [];
        $created = [];
        foreach ($roles as $role) {
            if ($role->user === '') {
                throw AccountError::CannotUser->error('CREATE ROLE', 'anonymous user');
            }
            if ($accounts->find($role) !== null || isset($created[$role->key()])) {
                $failed[] = $role;
                continue;
            }
            $created[$role->key()] = $role;
        }
        if ($failed !== [] && !$ifNotExists) {
            throw AccountError::CannotUser->error('CREATE ROLE', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }
        foreach ($failed as $identity) {
            $context->diagnostics->note(AccountError::UserAlreadyExists, AccountError::UserAlreadyExists->message($identity->quoted()));
        }
        foreach ($created as $role) {
            $accounts->add(Account::role($role));
            (new Names())->ascii($role, 8, $context->diagnostics);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
