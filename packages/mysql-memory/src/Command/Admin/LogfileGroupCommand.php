<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Executes CREATE, ALTER and DROP LOGFILE GROUP, which only NDB Cluster supports.
 *
 * Each commits the open transaction, checks its ENGINE option as the tablespace statements do,
 * then fails: InnoDB does not support log file groups (ER_FEATURE_UNSUPPORTED, followed by
 * ER_CHECK_NOT_IMPLEMENTED; verified on a live 8.4 server). MySQL 5.7 reports
 * ER_ILLEGAL_HA_CREATE_OPTION before ER_CHECK_NOT_IMPLEMENTED for CREATE, and
 * only the latter for ALTER or DROP. Modern releases check explicit table locks
 * after the ENGINE option and before reporting InnoDB support.
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        $session->transaction->commit();
        (new Storage\LegacySupport())->check($statement, $session, $context);
        $operationName = 'CREATE/ALTER/DROP LOGFILE GROUP';
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 && ($statement instanceof CreateLogfileGroup || $statement instanceof AlterLogfileGroup || $statement instanceof DropLogfileGroup)) {
            $engine = (new Storage\LegacySupport())->engine($statement->options, $session, $context);
            if ($engine !== 'InnoDB') {
                throw SchemaError::IllegalCreateOption->error($engine, 'TABLESPACE or LOGFILE GROUP');
            }
            $verb = $statement instanceof CreateLogfileGroup ? 'CREATE' : ($statement instanceof AlterLogfileGroup ? 'ALTER' : 'DROP');
            if ($statement instanceof CreateLogfileGroup) {
                throw new SqlError(SchemaError::IllegalCreateOption, SchemaError::IllegalCreateOption->message('InnoDB', 'LOGFILE GROUP'), null, [[SchemaError::EngineUnsupportedOperation->value, SchemaError::EngineUnsupportedOperation->message('CREATE LOGFILE GROUP')]]);
            }
            throw SchemaError::EngineUnsupportedOperation->error($verb . ' LOGFILE GROUP');
        }
        if ($statement instanceof CreateLogfileGroup || $statement instanceof AlterLogfileGroup || $statement instanceof DropLogfileGroup) {
            (new TablespaceCommand())->engine($statement->options, $operationName);
        }
        if ($session->locks !== []) {
            throw StatementError::LockedOrActiveTransaction->error();
        }
        throw new SqlError(AdministrationError::FeatureUnsupported, AdministrationError::FeatureUnsupported->message('LOGFILE GROUP', 'by InnoDB'), null, [[SchemaError::EngineUnsupportedOperation->value, SchemaError::EngineUnsupportedOperation->message($operationName)]]);
    }
}
