<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Statement\Operation;

/**
 * Executes BEGIN, START TRANSACTION, COMMIT and ROLLBACK.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 *
 * @visibility MySqlMemory
 */
final class TransactionCommand implements Command
{
    /**
     * Answers true.
     */
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Opens or ends the transaction of the session.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof Commit) {
            $session->transaction->commit();
        } elseif ($statement instanceof Rollback) {
            $session->transaction->rollback();
        } else {
            $session->transaction->commit();
            $session->transaction->begin();
        }

        return new Completion();
    }
}
