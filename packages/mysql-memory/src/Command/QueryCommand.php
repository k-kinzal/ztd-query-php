<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;

/**
 * Executes a query: SELECT, a set operation, VALUES or TABLE.
 *
 * @visibility MySqlMemory
 */
final class QueryCommand implements Command
{
    /**
     * Answers true: a query starts with an empty diagnostics area.
     */
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Plans and executes the query, answering its rows.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Query);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);

        return (new Output())->result($planner->query($statement, null), $context);
    }
}
