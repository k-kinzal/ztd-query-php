<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Statement\Operation;

/**
 * Executes GRANT of privileges, of roles and of PROXY.
 *
 * The statement commits the open transaction. GRANT of privileges checks its privileges and
 * level (see Levels), then the AS clause, whose account and roles must exist and be granted
 * (ER_INVALID_GRANT_AS, after ER_NO_SUCH_USER for a missing account), then warns of each host
 * name and refuses an account that does not exist, as GRANT never creates one
 * (ER_CANT_CREATE_USER_WITH_GRANT), except in MySQL 5.6 and 5.7, where it creates it; a dynamic privilege the server does not register is then a
 * syntax error. GRANT of roles refuses an account or a role that does not exist
 * (ER_UNKNOWN_AUTHID) and a grant that would make a role reach itself (ER_ROLE_GRANTED_TO_ITSELF).
 * PROXY grants require authority over the proxied account (see Proxies). Modern grants apply nothing when any recipient fails. Legacy grants retain
 * the grants for existing recipients and report each missing one (verified on 5.7 and 8.4).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 *
 * @visibility MySqlMemory
 */
final class GrantCommand implements Command
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
     * Grants the privileges or roles.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $session->transaction->commit();
        if ($statement instanceof GrantProxy) {
            (new Proxies())->grant($statement, $session);
        } else {
            assert($statement instanceof GrantPrivileges);
            $this->privileges($statement, $operation, $session, $context);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Grants privileges.
     *
     * @throws SqlError When the statement is refused
     */
    public function privileges(GrantPrivileges $statement, Operation $operation, Session $session, Context $context): void
    {
        $names = new Names($session->settings()->release());
        $names->check([...$names->users($statement->grantees), $statement->as?->user, ...$statement->as->roles->roles ?? []]);
        $levels = new Levels($session->settings()->release());
        $target = $levels->target($statement->kind, $statement->level, $session);
        $grantees = $this->identities($statement, $target[0], $session);
        $levels->parsed($operation, $session);
        $levels->objects($operation, $session, $statement->privileges, $target);
        if ($statement->as !== null) {
            $this->as($statement->as, $session);
        }
        foreach ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 ? $statement->grantees : [] as $grantee) {
            if ($grantee->identification?->credential === Credential::PasswordHash) {
                $session->diagnostics->warning(1287, "'IDENTIFIED BY PASSWORD' is deprecated and will be removed in a future release. Please use IDENTIFIED WITH <plugin> AS <hash> instead");
            }
        }
        $session->databaseGrants->refused($target);
        if ((new Catalog($session->settings()->release()))->legacy()) {
            (new Grant\LegacyGrants())->execute($statement, $operation, $grantees, $target, $session);
            return;
        }
        foreach ($this->existing($grantees, $session, $operation) as $account) {
            $this->apply($statement, $operation, $account, $target, $session);
        }
    }

    /**
     * Applies one resolved recipient's privileges after checking their level.
     *
     * @param array{string, string, string} $target The resolved privilege level
     * @throws SqlError When a privilege cannot be granted at this level
     */
    public function apply(GrantPrivileges $statement, Operation $operation, Account $account, array $target, Session $session): void
    {
        $levels = new Levels($session->settings()->release());
        $levels->usage($operation);
        [$static, $columns, $dynamic, $option, $all] = $levels->read($statement->privileges, $target[0]);
        foreach ($dynamic as $name) {
            if (!(new Catalog($session->settings()->release()))->registered($name)) {
                throw StatementError::SyntaxError->error();
            }
        }
        $named = $static !== [] || $columns !== [] || $all || $option || ($dynamic === [] && array_filter($statement->privileges, static fn ($privilege): bool => $privilege instanceof StaticPrivilege) !== []);
        $option = $option || array_filter($statement->options, static fn ($with): bool => $with instanceof GrantOptionRight) !== [];
        $this->conversions($account->identity, $target[0], count($dynamic), $session);
        $this->grant($account, $target, $static, $columns, $dynamic, $option, $all, $named);
        $session->databaseGrants->remember($account, $target);
    }

    /**
     * Resolves every modern recipient and retains one error for each absent occurrence.
     *
     * @param list<Identity> $grantees The recipients in written order, including duplicates
     * @return list<Account>
     * @throws SqlError When any recipient does not exist
     */
    public function existing(array $grantees, Session $session, ?Operation $operation = null): array
    {
        $found = [];
        $failure = null;
        foreach ($grantees as $identity) {
            try {
                $account = $session->instance->accounts->find($identity) ?? throw AccountError::CantCreateUserWithGrant->error();
                if ($operation !== null) {
                    (new Levels($session->settings()->release()))->usage($operation);
                }
                $found[] = $account;
            } catch (SqlError $error) {
                $failure ??= $error;
                $session->diagnostics->error($error->getCode(), $error->getMessage());
            }
        }
        if ($failure !== null) {
            $session->instance->accounts->reload();
            throw new SqlError($failure->error, $failure->getMessage(), recorded: true);
        }
        return $found;
    }

    /**
     * Resolves recipient hostnames except for routine grants, which do not consult name resolution.
     *
     * @return list<Identity> The complete recipients in written order
     */
    public function identities(GrantPrivileges $statement, string $kind, Session $session): array
    {
        $names = new Names($session->settings()->release());
        $grantees = [];
        foreach ($statement->grantees as $grantee) {
            $identity = $names->identity($grantee->user, $session);
            if (!in_array($kind, ['PROCEDURE', 'FUNCTION'], true)) {
                $names->resolve($identity, $session->diagnostics);
            }
            $grantees[] = $identity;
        }
        return $grantees;
    }

    /**
     * Records the recipient Host conversion at each grant-row access.
     */
    public function conversions(Identity $identity, string $kind, int $dynamic, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $names->ascii($identity, match ($kind) {
            'DATABASE' => 3, 'TABLE' => 2, default => 1
        }, $session->diagnostics);
        $names->ascii($identity, $dynamic, $session->diagnostics, 'HOST');
    }

    /**
     * Answers the accounts GRANT names in MySQL 5.6 and 5.7, creating those that do not exist and applying the authentication, TLS requirement and resource limits the statement gives.
     *
     * Under NO_AUTO_CREATE_USER an account that does not exist is created only with a password
     * or an authentication string that is not empty (ER_PASSWORD_NO_MATCH otherwise). MySQL 5.7
     * warns, for each account, that creating it with GRANT or changing more than its privileges is
     * deprecated (verified on live 5.6.51 and 5.7.44 servers).
     * Source: https://dev.mysql.com/doc/refman/5.7/en/grant.html.
     *
     * @param list<Identity> $grantees The accounts the statement names, in order
     * @return list<Account>
     *
     * @throws SqlError When an account cannot be created, or a plugin or an authentication string is refused
     */
    public function accounts(GrantPrivileges|GrantProxy $statement, array $grantees, Session $session): array
    {
        $release = $session->settings()->release();
        $warns = $release === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $accounts = $session->instance->accounts;
        $options = new Options($release);
        $plugin = (new Credentials($release))->default();
        $limits = $statement instanceof GrantPrivileges ? array_values(array_filter($statement->options, static fn ($with): bool => $with instanceof ResourceLimit)) : [];
        $tls = $statement instanceof GrantPrivileges ? $statement->tls : null;
        $found = [];
        foreach ($statement->grantees as $index => $grantee) {
            $identity = $grantees[$index];
            $identification = $grantee->identification;
            $account = $accounts->find($identity);
            if ($account === null && $session->modes()->has('NO_AUTO_CREATE_USER') && ($identification === null || $identification->credential === Credential::None || ($identification->secret->value ?? '') === '')) {
                throw AccountError::PasswordNoMatch->error();
            }
            $options->check($identification, $account->plugin ?? $plugin);
            if ($account === null) {
                $account = new Account($identity, $plugin);
                $accounts->add($account);
                if ($warns) {
                    $session->diagnostics->warning(1287, 'Using GRANT for creating new user is deprecated and will be removed in future release. Create new user with CREATE USER statement.');
                }
            } elseif ($warns && ($identification !== null || $tls !== null || $limits !== [])) {
                $session->diagnostics->warning(1287, "Using GRANT statement to modify existing user's properties other than privileges is deprecated and will be removed in future release. Use ALTER USER statement for this operation.");
            }
            if ($identification !== null) {
                $options->identify($account, $identification, false);
            }
            $options->apply($account, $tls, $limits, [], null);
            $found[] = $account;
        }

        return $found;
    }

    /**
     * Grants what a privilege list names at a level to an account; WITH GRANT OPTION reaches the static privileges of the level only when the list names one, or names USAGE and no dynamic privilege.
     *
     * @param array{string, string, string} $target
     * @param list<string> $static
     * @param array<string, list<string>> $columns
     * @param list<string> $dynamic
     */
    public function grant(Account $account, array $target, array $static, array $columns, array $dynamic, bool $option, bool $all, bool $named = true): void
    {
        foreach ($dynamic as $name) {
            $account->grants->dynamic[$name] = ($account->grants->dynamic[$name] ?? false) || $option;
        }
        $option = $option && $named;
        if ($static === [] && $columns === [] && !$option && !$all) {
            return;
        }
        $privileges = (new Levels())->at($account->grants, $target, true);
        assert($privileges !== null);
        $privileges->add($static);
        foreach ($columns as $name => $listed) {
            $privileges->addColumns($name, $listed);
        }
        $privileges->grantOption = $privileges->grantOption || $option;
        $account->grants->prune();
    }

    /**
     * Checks the AS clause: the account must exist, and the roles it names must be granted to it.
     *
     * @throws SqlError When the account or a role is invalid
     */
    public function as(GrantAs $as, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $identity = $names->identity($as->user, $session);
        $accounts = $session->instance->accounts;
        if ($accounts->find($identity) === null) {
            $session->diagnostics->error(AccountError::NoSuchUser->value, $identity->missingDefiner($session->settings()->legacy()));

            throw AccountError::InvalidGrantAs->error();
        }
        if ($as->roles?->set !== RoleSet::Named) {
            return;
        }
        $granted = $accounts->roles($identity);
        foreach ($as->roles->roles as $role) {
            if (!isset($granted[$names->identity($role, $session)->key()])) {
                throw AccountError::InvalidGrantAs->error();
            }
        }
    }

}
