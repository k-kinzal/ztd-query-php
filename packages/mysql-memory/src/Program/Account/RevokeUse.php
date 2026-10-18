<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Account;

use MySqlMemory\Command\Account\Levels;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;

/**
 * Resolves a complete retained revocation when its instruction is reached.
 * The invocation commits the transaction before reporting its target condition.
 *
 * @visibility MySqlMemory
 */
final class RevokeUse
{
    /**
     * Resolves account spelling and the target's default database during preparation.
     */
    public function header(DeferredRevokePrivileges $request, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $names->check($names->users($request->users));
        (new Levels($session->settings()->release()))->target($request->kind, $request->level, $session);
    }

    /**
     * Executes the revocation's implicit commit and target resolution without reparsing.
     *
     * @throws SqlError When a required name or the retained routine target is refused
     */
    public function raise(DeferredRevokePrivileges $request, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            $session->diagnostics->clear();
        }
        $this->header($request, $session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_revoke');
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
        foreach ($request->users as $user) {
            $names->resolve($names->identity($user->user, $session), $session->diagnostics);
        }
        throw AccountError::IllegalGrantForTable->error();
    }
}
