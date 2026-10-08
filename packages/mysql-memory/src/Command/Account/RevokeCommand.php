<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
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
 * host name. An account that does not exist is ER_NONEXISTING_GRANT, or with IGNORE UNKNOWN USER
 * a warning. A database, table or routine the account holds no grant on is ER_NONEXISTING_GRANT,
 * ER_NONEXISTING_TABLE_GRANT or ER_NONEXISTING_PROC_GRANT, a warning under IF EXISTS; the global
 * level never is. ALL at the global level revokes every privilege at every level. REVOKE of
 * roles refuses an account or a role that does not exist (ER_UNKNOWN_AUTHID, for a role a
 * warning under IF EXISTS); revoking a role that is not granted does nothing. REVOKE ALL, GRANT
 * OPTION keeps the roles and refuses an account that does not exist (ER_REVOKE_GRANTS). REVOKE
 * PROXY of an account is refused, as the account of the session holds no PROXY privilege.
 * Nothing is revoked when the statement fails (verified on a live 8.4 server).
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
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
            $accounts->restore($saved);
            throw $error;
        }

        return new Completion(0, 0, $context->diagnostics->count());
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
        $names = new Names();
        $names->check($names->users($statement->users));
        $levels = new Levels();
        $levels->parsed($operation, $session);
        $target = $levels->target($statement->kind, $statement->level, $session);
        $levels->dynamic($statement->privileges, $target, $session, $statement->ifExists);
        $found = $this->found($names->users($statement->users), $statement->ignoreUnknownUser, $session, true);
        $levels->usage($operation);
        [$static, $columns, $dynamic, $option, $all] = $levels->read($statement->privileges, $target[0]);
        foreach ($dynamic as $name) {
            if (!(new Catalog())->registered($name)) {
                $session->diagnostics->warning(AccountError::UnregisteredDynamicPrivilege, AccountError::UnregisteredDynamicPrivilege->message($name));
            }
        }
        foreach ($found as $account) {
            foreach ($target[0] === 'GLOBAL' ? $dynamic : [] as $name) {
                unset($account->grants->dynamic[$name]);
            }
            if ($target[0] === 'GLOBAL' && $all) {
                $account->grants->clear();
                continue;
            }
            $this->revoke($account, $target, $static, $columns, $option, $all, $statement->ifExists, $session);
        }
    }

    /**
     * Revokes what a privilege list names at a level from an account.
     *
     * @param array{string, string, string} $target
     * @param list<string> $static
     * @param array<string, list<string>> $columns
     *
     * @throws SqlError When the account holds no grant at the level
     */
    public function revoke(Account $account, array $target, array $static, array $columns, bool $option, bool $all, bool $ifExists, Session $session): void
    {
        if ($static === [] && $columns === [] && !$option && !$all) {
            return;
        }
        $privileges = (new Levels())->at($account->grants, $target, false);
        if ($target[0] !== 'GLOBAL' && ($privileges === null || !$this->holds($privileges, $static, $columns, $option, $all))) {
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
        $privileges->remove($all ? (new Levels())->all($target[0]) : $static);
        foreach ($columns as $name => $listed) {
            $privileges->removeColumns($name, $listed);
        }
        $privileges->grantOption = $privileges->grantOption && !$option;
        $account->grants->prune();
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
     * Answers the error of a level an account holds no grant on.
     *
     * @param array{string, string, string} $target
     */
    public function missing(Identity $identity, array $target): SqlError
    {
        return match ($target[0]) {
            'DATABASE', 'GLOBAL' => AccountError::NonexistingGrant->error($identity->user, $identity->host),
            'TABLE' => AccountError::NonexistingTableGrant->error($identity->user, $identity->host, $target[2]),
            default => AccountError::NonexistingRoutineGrant->error($identity->user, $identity->host, mb_strtolower($target[2], 'UTF-8')),
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
        $names = new Names();
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
     * @throws SqlError When an account or a role does not exist
     */
    public function roles(RevokeRoles $statement, Operation $operation, Session $session): void
    {
        $names = new Names();
        $listed = array_map(static fn ($role): ?AccountName => $role instanceof GrantedRole ? $role->role : null, $statement->roles);
        $names->check([...$listed, ...$statement->users]);
        (new Levels())->parsed($operation, $session);
        $accounts = $session->instance->accounts;
        foreach ($statement->users as $user) {
            $identity = $names->identity($user, $session);
            if ($accounts->find($identity) === null && !$statement->ignoreUnknownUser) {
                throw AccountError::UnknownAuthorizationId->error($identity->backquoted());
            }
        }
        $roles = [];
        foreach ($listed as $role) {
            $identity = $role === null ? null : $names->identity($role, $session);
            if ($identity !== null && $accounts->find($identity) === null) {
                if (!$statement->ifExists) {
                    throw AccountError::UnknownAuthorizationId->error($identity->backquoted());
                }
                $session->diagnostics->warning(AccountError::UnknownAuthorizationId, AccountError::UnknownAuthorizationId->message($identity->backquoted()));
                continue;
            }
            $roles[] = $identity;
        }
        $found = $this->found($statement->users, true, $session, false);
        foreach ($found as $account) {
            foreach ($roles as $role) {
                if ($role !== null) {
                    $accounts->revoke($role, $account->identity);
                }
            }
        }
    }

    /**
     * Revokes PROXY: refused for any account that exists, as the account of the session holds no PROXY privilege.
     *
     * @throws SqlError When an account does not exist or exists
     */
    public function proxy(RevokeProxy $statement, Session $session): void
    {
        $names = new Names();
        $names->check([$statement->proxied, ...$names->users($statement->users)]);
        $names->resolve($names->identity($statement->proxied, $session), $session->diagnostics);
        [$user, $host] = explode('@', $session->variables->account, 2) + [1 => ''];
        $denied = AccountError::AccessDeniedNoPassword->error($user, $host);
        if ($this->found($names->users($statement->users), $statement->ignoreUnknownUser, $session, true, $denied) !== []) {
            throw $denied;
        }
    }

    /**
     * Revokes every privilege at every level from accounts, keeping their roles.
     *
     * @throws SqlError When an account does not exist
     */
    public function everything(RevokeAll $statement, Session $session): void
    {
        $names = new Names();
        $users = $names->users($statement->users);
        $names->check($users);
        foreach ($this->found($users, $statement->ignoreUnknownUser, $session, false, AccountError::RevokeGrants->error()) as $account) {
            $account->grants->clear();
        }
    }
}
