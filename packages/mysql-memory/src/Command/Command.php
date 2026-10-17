<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Operation;

/**
 * Executes one kind of statement once it is resolved, as a command of the server executes its statement.
 *
 * @visibility MySqlMemory
 */
interface Command
{
    /**
     * Tells whether the statement starts with an empty diagnostics area; SHOW WARNINGS and SHOW ERRORS do not.
     */
    public function clearsDiagnostics(): bool;

    /**
     * Executes a resolved statement in a session.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement fails
     */
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply;
}
