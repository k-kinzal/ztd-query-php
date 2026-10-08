<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

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
use SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\SetDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Statement\Operation;

/**
 * Executes SET ROLE, SET DEFAULT ROLE and ALTER USER … DEFAULT ROLE.
 *
 * SET ROLE makes roles granted to the account of the session active in the session: its
 * default roles, none, all of them, all except some, or roles it names, each of which must be
 * granted to it directly (ER_ROLE_NOT_GRANTED). SET DEFAULT ROLE commits the open transaction
 * and sets the roles an account activates when it connects: NONE for any account, even one that
 * does not exist; ALL for accounts that exist (ER_UNKNOWN_AUTHID, then ER_FAILED_DEFAULT_ROLES);
 * or named roles, each granted to the account (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-role.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-default-role.html.
 *
 * @visibility MySqlMemory
 */
final class RoleCommand implements Command
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
     * Sets the roles.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $names = new Names($session->settings()->release());
        if ($statement instanceof SetRole) {
            $names->check($statement->roles->roles);
            $session->variables->roles = $this->selected($statement->roles, new Identity($session->user, '%'), $session);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $session->transaction->commit();
        assert($statement instanceof SetDefaultRole || $statement instanceof AlterDefaultRole);
        $users = $statement instanceof SetDefaultRole ? $statement->users : [$statement->user];
        $names->check([...$statement->roles->roles, ...$users]);
        $accounts = $session->instance->accounts;
        $chosen = [];
        foreach ($users as $user) {
            $identity = $names->identity($user, $session);
            if ($statement->roles->set === RoleSet::All && $accounts->find($identity) === null) {
                throw new SqlError(AccountError::UnknownAuthorizationId, AccountError::UnknownAuthorizationId->message($identity->backquoted()), null, [[AccountError::FailedDefaultRoles->value, AccountError::FailedDefaultRoles->message()]]);
            }
            $chosen[] = [$identity, $this->selected($statement->roles, $identity, $session)];
        }
        foreach ($chosen as [$identity, $roles]) {
            unset($accounts->defaults[$identity->key()]);
            foreach ($roles as $role) {
                $accounts->defaults[$identity->key()][$role->key()] = $role;
            }
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Answers the roles a selection chooses among those granted to an account.
     *
     * @return list<Identity>
     *
     * @throws SqlError When a named role is not granted to the account
     */
    public function selected(RoleSelection $selection, Identity $account, Session $session): array
    {
        $names = new Names($session->settings()->release());
        $accounts = $session->instance->accounts;
        $granted = array_map(static fn (array $role): Identity => $role[0], $accounts->roles($account));
        $roles = match ($selection->set) {
            RoleSet::None => [],
            RoleSet::Default => array_values($accounts->defaults[$account->key()] ?? []),
            RoleSet::All => array_values(array_diff_key($granted, array_flip(array_map(static fn ($role): string => $names->identity($role, $session)->key(), $selection->roles)))),
            RoleSet::Named => array_map(static fn ($role): Identity => $names->identity($role, $session), $selection->roles),
        };
        foreach ($selection->set === RoleSet::Named ? $roles : [] as $role) {
            if (!isset($granted[$role->key()])) {
                throw AccountError::RoleNotGranted->error($role->backquoted(), $account->backquoted());
            }
        }
        usort($roles, static fn (Identity $a, Identity $b): int => strcmp($a->backquoted(), $b->backquoted()));

        return $roles;
    }
}
