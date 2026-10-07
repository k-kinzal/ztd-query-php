<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\FieldType;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW WARNINGS and SHOW ERRORS: the conditions of the diagnostics area.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 *
 * @visibility MySqlMemory
 */
final class WarningsCommand implements Command
{
    /**
     * Answers false: the statement reads the area the last statement left.
     */
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return false;
    }

    /**
     * Answers the conditions.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $errors = $operation->statement instanceof ShowErrors;
        $rows = [];
        foreach ($session->diagnostics->conditions as [$level, $code, $message]) {
            if (!$errors || $level === 'Error') {
                $rows[] = [$level, (string) $code, $message];
            }
        }
        $columns = [
            new ResultColumn('Level', FieldType::VarString, 28, 31, ColumnFlag::NotNull->value, 33),
            new ResultColumn('Code', FieldType::Long, 4, 0, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value, 63),
            new ResultColumn('Message', FieldType::VarString, 2048, 31, ColumnFlag::NotNull->value, 33),
        ];

        return new ResultSet($columns, $rows);
    }
}
