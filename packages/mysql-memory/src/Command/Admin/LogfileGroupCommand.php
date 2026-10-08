<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE, ALTER and DROP LOGFILE GROUP, which only NDB Cluster supports.
 *
 * Each commits the open transaction, checks its ENGINE option as the tablespace statements do,
 * then fails: InnoDB does not support log file groups (ER_FEATURE_UNSUPPORTED, followed by
 * ER_CHECK_NOT_IMPLEMENTED; verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 *
 * @visibility MySqlMemory
 */
final class LogfileGroupCommand implements Command
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
     * Refuses the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        $operationName = 'CREATE/ALTER/DROP LOGFILE GROUP';
        if ($statement instanceof CreateLogfileGroup || $statement instanceof AlterLogfileGroup || $statement instanceof DropLogfileGroup) {
            (new TablespaceCommand())->engine($statement->options, $operationName);
        }

        throw new SqlError(AdministrationError::FeatureUnsupported, AdministrationError::FeatureUnsupported->message('LOGFILE GROUP', 'by InnoDB'), null, [[SchemaError::EngineUnsupportedOperation->value, SchemaError::EngineUnsupportedOperation->message($operationName)]]);
    }
}
