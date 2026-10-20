<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Account;

use MySqlMemory\Command\Account\Role\Grants;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles;

/**
 * Reaches a complete stored or prepared role grant through account execution.
 *
 * @visibility MySqlMemory
 */
final class RoleGrantUse
{
    /**
     * Preserves the implicit commit and ordinary account/error ordering.
     */
    public function run(DeferredGrantRoles $request, Session $session, bool $invocation = true): Reply
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_grant');
        $session->transaction->commit();
        $grants = new Grants();
        $grants->header($request, $session);
        $grants->apply($request, $session);
        return new Completion(0, 0, $session->diagnostics->raised());
    }
}
