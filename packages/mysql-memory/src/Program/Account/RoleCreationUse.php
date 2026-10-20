<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Account;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole;

/**
 * Reaches a complete stored role definition through the ordinary account engine.
 *
 * @visibility MySqlMemory
 */
final class RoleCreationUse
{
    /**
     * Preserves account ordering, warnings and the implicit transaction boundary.
     */
    public function run(DeferredCreateRole $request, Session $session, bool $invocation = true): Reply
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_role');
        $session->transaction->commit();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        return (new \MySqlMemory\Command\Account\Role\Definitions())->execute($request, $session, $context);
    }
}
