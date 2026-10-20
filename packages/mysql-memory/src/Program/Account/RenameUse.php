<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Account;

use MySqlMemory\Command\Account\RenameUserCommand;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser;

/**
 * Reaches a complete ordered account rename through ordinary account execution.
 *
 * @visibility MySqlMemory
 */
final class RenameUse
{
    /**
     * Preserves the transaction boundary, identity changes and legacy diagnostics.
     */
    public function run(DeferredRenameUser $request, Session $session, bool $invocation = true): Reply
    {
        $command = new RenameUserCommand();
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            if ((new \MySqlMemory\Session\Execution($session))->retains($request, $command)) {
                $session->diagnostics->retain();
            } else {
                $session->diagnostics->clear();
            }
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_rename_user');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        return $command->apply($request, $session, $context);
    }
}
