<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Command\Account\Revoke\Recipients;
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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Operation;

/**
 * Executes REVOKE of privileges, of roles, of PROXY, and of ALL, GRANT OPTION.
 *
 * The statement commits the open transaction. REVOKE of privileges checks its privileges and
 * level as GRANT does, though a dynamic privilege below the global level is only a warning under
 * IF EXISTS and a dynamic privilege the server does not register is a warning; it warns of each
 * host name. Missing accounts and grants are diagnosed once per occurrence, in account order,
 * including after an error; IGNORE UNKNOWN USER warns about missing accounts. A database,
 * table or routine the account holds no grant on is ER_NONEXISTING_GRANT,
 * ER_NONEXISTING_TABLE_GRANT or ER_NONEXISTING_PROC_GRANT, a warning under IF EXISTS; the global
 * level never is. In 8.x and later, ALL at the global level revokes every privilege at every
 * level, using the ALL account checks without per-host warnings; in 5.x it removes only global static privileges. REVOKE of
 * roles refuses an account or a role that does not exist (ER_UNKNOWN_AUTHID, for a role a
 * warning under IF EXISTS); revoking a role that is not granted does nothing. REVOKE ALL, GRANT
 * OPTION keeps the roles. In 5.x every missing account causes ER_REVOKE_GRANTS,
 * while successful recipients keep their changes. In 8.x and later the final
 * recipient determines that error and a failure rolls back the whole statement. REVOKE
 * PROXY requires authority over the proxied account (see Proxies).
 * A failed privilege or proxy REVOKE keeps successful changes in 5.x and rolls them back in 8.x and later
 * (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html.
 *
 * @visibility MySqlMemory
 */
final class RevokeCommand implements Command
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
     * Revokes the privileges or roles.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $session->transaction->commit();
        $accounts = $session->instance->accounts;
        $saved = $accounts->copy();
        try {
            match (true) {
                $statement instanceof RevokeRoles => $this->roles($statement, $operation, $session),
                $statement instanceof RevokeProxy => $this->proxy($statement, $session),
                $statement instanceof RevokeAll => $this->everything($statement, $session),
                default => $this->privileges($operation, $session),
            };
        } catch (SqlError $error) {
            if ((!$statement instanceof RevokePrivileges && !$statement instanceof RevokeProxy && !$statement instanceof RevokeAll) || !$session->settings()->legacy()) {
                $accounts->restore($saved);
                $accounts->reload();
            }
            throw $error;
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Revokes privileges.
     *
     * @throws SqlError When the statement is refused
     */
    public function privileges(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        assert($statement instanceof RevokePrivileges);
        if (!$session->settings()->legacy() && $statement->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel && count($statement->privileges) === 1 && $statement->privileges[0] instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges) {
            $this->everything($statement, $session);
            return;
        }
        $names = new Names($session->settings()->release());
        $names->check($names->users($statement->users));
        $levels = new Levels($session->settings()->release());
        $target = $levels->target($statement->kind, $statement->level, $session);
        foreach ($statement->users as $user) {
            $names->resolve($names->identity($user->user, $session), $session->diagnostics);
        }
        if ($statement->kind === \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table || $statement->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel) {
            $levels->dynamic($statement->privileges, $target, $session, $statement->ifExists);
        }
        $levels->parsed($operation, $session);
        (new Recipients())->run($statement, $operation, $target, $session);
    }

    /**
     * Revokes what a privilege list names at a level from an account.
     *
     * @param array{string, string, string} $target
     * @param list<string> $static
     * @param array<string, list<string>> $columns
     *
     * MySQL 5.6, 5.7 and 8.0 accept a missing individual routine privilege when
     * the account has another grant on that routine. MySQL 8.4 and 9.1 check
     * the requested privilege. Diagnostic names retain their written case.
     *
     * @throws SqlError When the account holds no grant at the level
     */
    public function revoke(Account $account, array $target, array $static, array $columns, bool $option, bool $all, bool $ifExists, Session $session): void
    {
        $empty = $static === [] && $columns === [] && !$option && !$all;
        $release = $session->settings()->release();
        if ($empty && ($target[0] === 'GLOBAL' || ($target[0] === 'DATABASE' && ($release === GrammarRelease::MySql847 || $release === GrammarRelease::MySql910)))) {
            if ($target[0] === 'DATABASE') {
                $session->databaseGrants->reuse($account, $target[1]);
            }

            return;
        }
        $privileges = (new Levels($session->settings()->release()))->at($account->grants, $target, false);
        $checksHeld = $this->requiresHeld($target[0], $release);
        if ($target[0] !== 'GLOBAL' && ($privileges === null || ($checksHeld && !$empty && !$this->holds($privileges, $static, $columns, $option, $all)))) {
            $session->databaseGrants->refused($target);
            $error = $this->missing($account->identity, $target);
            if (!$ifExists) {
                throw $error;
            }
            $session->diagnostics->warning($error->error, $error->getMessage());

            return;
        }
        if ($privileges === null) {
            return;
        }
        $privileges->remove($all ? (new Levels($session->settings()->release()))->all($target[0]) : $static);
        foreach ($columns as $name => $listed) {
            $privileges->removeColumns($name, $listed);
        }
        $privileges->grantOption = $privileges->grantOption && !$option;
        $account->grants->prune();
        $session->databaseGrants->remember($account, $target);
    }

    /**
     * Tells whether a level holds something a REVOKE names: ALL needs only the grant, a column privilege must be held on each column it names, and a privilege may be held on the level or on a column.
     *
     * @param list<string> $static
     * @param array<string, list<string>> $columns
     */
    public function holds(Privileges $privileges, array $static, array $columns, bool $option, bool $all): bool
    {
        foreach ($columns as $name => $listed) {
            foreach ($listed as $column) {
                if (!isset($privileges->columns[$column][$name])) {
                    return false;
                }
            }
        }
        if ($all || $columns !== [] || ($option && $privileges->grantOption)) {
            return true;
        }

        foreach ($static as $name) {
            if (isset($privileges->names[$name]) || $privileges->columnsOf($name) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether this release checks each named privilege of an existing routine grant.
     */
    public function requiresHeld(string $kind, GrammarRelease $release): bool
    {
        return in_array($release, [GrammarRelease::MySql847, GrammarRelease::MySql910], true) || !in_array($kind, ['PROCEDURE', 'FUNCTION'], true);
    }

    /**
     * Answers the error of a level an account holds no grant on, preserving the written object name.
     *
     * @param array{string, string, string} $target
     */
    public function missing(Identity $identity, array $target): SqlError
    {
        return match ($target[0]) {
            'DATABASE', 'GLOBAL' => AccountError::NonexistingGrant->error($identity->user, $identity->host),
            'TABLE' => AccountError::NonexistingTableGrant->error($identity->user, $identity->host, $target[2]),
            default => AccountError::NonexistingRoutineGrant->error($identity->user, $identity->host, $target[2]),
        };
    }

    /**
     * Answers the accounts a REVOKE names; an account that does not exist is refused, or skipped with a warning under IGNORE UNKNOWN USER.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Name\Account|\SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser> $users
     * @param bool $hosts Whether each host name is warned of first
     * @param SqlError|null $unknown The error of an account that does not exist, or null for ER_NONEXISTING_GRANT
     * @return list<Account>
     *
     * @throws SqlError When an account does not exist
     */
    public function found(array $users, bool $ignore, Session $session, bool $hosts, ?SqlError $unknown = null): array
    {
        $names = new Names($session->settings()->release());
        $identities = array_map(static fn ($user): Identity => $names->identity($user, $session), $users);
        foreach ($hosts ? $identities : [] as $identity) {
            $names->resolve($identity, $session->diagnostics);
        }
        $found = [];
        $ignored = [];
        foreach ($identities as $identity) {
            $account = $session->instance->accounts->find($identity);
            if ($account !== null) {
                $found[] = $account;
            } elseif ($ignore) {
                $ignored[] = $identity;
            } else {
                throw $unknown ?? AccountError::NonexistingGrant->error($identity->user, $identity->host);
            }
        }
        foreach ($ignored as $identity) {
            $session->diagnostics->warning(AccountError::UserDoesNotExist, AccountError::UserDoesNotExist->message($identity->user));
        }

        return $found;
    }

    /**
     * Revokes roles.
     *
     * IF EXISTS warns of each role that does not exist once for each account the statement names
     * that exists, as often as it names it; an account that does not exist warns of itself in its
     * place under IGNORE UNKNOWN USER (verified on a live 8.4 server).
     *
     * @throws SqlError When an account or a role does not exist
     */
    public function roles(RevokeRoles $statement, Operation $operation, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $listed = array_map(static fn (GrantedRole $role): AccountName => $role->role, $statement->roles);
        $names->check([...$listed, ...$statement->users]);
        (new Levels($session->settings()->release()))->parsed($operation, $session);
        $accounts = $session->instance->accounts;
        foreach ($statement->users as $user) {
            $identity = $names->identity($user, $session);
            if ($accounts->find($identity) === null) {
                if (!$statement->ignoreUnknownUser) {
                    throw AccountError::UnknownAuthorizationId->error($identity->authorization());
                }
                $session->diagnostics->warning(AccountError::UserDoesNotExist, AccountError::UserDoesNotExist->message($identity->user));
                continue;
            }
            foreach ($listed as $role) {
                $source = $names->identity($role, $session);
                if ($accounts->find($source) === null) {
                    if (!$statement->ifExists) {
                        throw AccountError::UnknownAuthorizationId->error($source->authorization());
                    }
                    $session->diagnostics->warning(AccountError::UnknownAuthorizationId, AccountError::UnknownAuthorizationId->message($source->authorization()));
                    continue;
                }
                $accounts->revoke($source, $identity);
            }
        }
    }

    /**
     * Revokes the named PROXY grants, warning of absent grants under IF EXISTS.
     *
     * @throws SqlError When the session lacks authority or a named grant does not exist
     */
    public function proxy(RevokeProxy $statement, Session $session): void
    {
        (new Proxies())->revoke($statement, $session);
    }

    /**
     * Revokes every privilege at every level from accounts, keeping their roles.
     *
     * @throws SqlError When the versioned recipient policy refuses an absent account
     */
    public function everything(RevokeAll|RevokePrivileges $statement, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $users = $names->users($statement->users);
        $names->check($users);
        $failed = false;
        foreach ($users as $user) {
            $identity = $names->identity($user, $session);
            $account = $session->instance->accounts->find($identity);
            if (!$session->settings()->legacy()) {
                $failed = false;
            }
            if ($account !== null) {
                $account->grants->clear();
            } elseif ($statement->ignoreUnknownUser) {
                $session->diagnostics->warning(AccountError::UserDoesNotExist, AccountError::UserDoesNotExist->message($identity->user));
            } else {
                $failed = true;
            }
        }
        if ($failed) {
            throw AccountError::RevokeGrants->error();
        }
    }
}
