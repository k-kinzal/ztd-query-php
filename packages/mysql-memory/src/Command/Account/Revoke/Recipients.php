<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Revoke;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Levels;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Account\RevokeCommand;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Statement\Operation;

/**
 * Applies a privilege REVOKE to each account occurrence, preserving diagnostic order.
 *
 * Host warnings precede account processing. Each occurrence may add an error or a warning;
 * the first error is returned after every occurrence has been checked. Dynamic privileges
 * are processed only at the global level and only until the first error. IF EXISTS tolerates
 * missing grants, but does not tolerate missing accounts. The latter report the target-level
 * error in modern releases and ER_NONEXISTING_GRANT in 5.x. Modern global ALL tolerates them.
 * Verified against fresh 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html.
 *
 * @visibility MySqlMemory
 */
final class Recipients
{
    /**
     * Visits every named account, returning the first error without recording it twice.
     *
     * @param array{string, string, string} $target
     * @throws SqlError When any account occurrence is refused
     */
    public function run(RevokePrivileges $statement, Operation $operation, array $target, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $identities = array_map(static fn ($user): Identity => $names->identity($user, $session), $names->users($statement->users));
        $first = null;
        foreach ($identities as $identity) {
            try {
                $this->apply($identity, $statement, $operation, $target, $session, $first !== null);
            } catch (SqlError $error) {
                $session->databaseGrants->refused($target);
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
            }
        }
        if ($first !== null) {
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }
    }

    /**
     * Applies the request to one account, including an occurrence after an earlier failure.
     *
     * @param array{string, string, string} $target
     * @throws SqlError When the account is missing or holds no matching grant
     */
    public function apply(Identity $identity, RevokePrivileges $statement, Operation $operation, array $target, Session $session, bool $failed): void
    {
        $levels = new Levels($session->settings()->release());
        [$static, $columns, $dynamic, $option, $all] = $levels->read($statement->privileges, $target[0]);
        $globalAll = $target[0] === 'GLOBAL' && $all && !$session->settings()->legacy();
        $account = $session->instance->accounts->find($identity);
        if ($account === null) {
            if ($statement->ignoreUnknownUser) {
                $session->diagnostics->warning(AccountError::UserDoesNotExist, AccountError::UserDoesNotExist->message($identity->user));
            } elseif (!$globalAll) {
                throw $session->settings()->legacy()
                    ? AccountError::NonexistingGrant->error($identity->user, $identity->host)
                    : (new RevokeCommand())->missing($identity, $target);
            }

            return;
        }
        $levels->usage($operation);
        if ($globalAll) {
            $account->grants->clear();

            return;
        }
        (new RevokeCommand())->revoke($account, $target, $static, $columns, $option, $all, $statement->ifExists, $session);
        if (!$failed && $target[0] === 'GLOBAL') {
            $this->dynamic($account, $dynamic, $session);
        }
    }

    /**
     * Revokes dynamic privileges, warning per account about each unregistered privilege.
     *
     * @param list<string> $privileges
     */
    public function dynamic(Account $account, array $privileges, Session $session): void
    {
        $catalog = new Catalog($session->settings()->release());
        foreach ($privileges as $name) {
            if (!$catalog->registered($name)) {
                $session->diagnostics->warning(AccountError::UnregisteredDynamicPrivilege, AccountError::UnregisteredDynamicPrivilege->message($name));
            }
            unset($account->grants->dynamic[$name]);
        }
    }
}
