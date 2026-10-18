<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Statement\Node;

/**
 * Preserves the additional argument diagnostic of a failed PURGE moment preparation.
 *
 * Name resolution, operand width and aggregate/window preparation failures are
 * followed by ER_WRONG_ARGUMENTS. Input refusals and evaluation failures are
 * reported at their own stages. A missing connection database precedes this
 * preparation. Verified on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class PurgeErrors
{
    /**
     * Adds the argument condition after the original preparation error.
     *
     * @param bool $routineDatabaseResolved Whether a failed stored call has a qualified or current database; without one MySQL 5.6 reports the missing function before this stage
     */
    public function prepared(SqlError $error, Node $statement, bool $routineDatabaseResolved = false): SqlError
    {
        return $statement instanceof PurgeLogsBefore ? $this->moment($error, $routineDatabaseResolved) : $error;
    }

    /**
     * Completes a cutoff preparation error, including a retained non-scalar operand.
     */
    public function moment(SqlError $error, bool $routineDatabaseResolved = false): SqlError
    {
        if ($error->error === QueryError::NoDatabase || ($error->error === ProgramError::RoutineMissing && !$routineDatabaseResolved)) {
            return $error;
        }
        $condition = [StatementError::WrongArguments->number(), StatementError::WrongArguments->message('PURGE LOGS BEFORE')];
        if (in_array($condition, $error->following, true)) {
            return $error;
        }
        $following = [...$error->following, $condition];

        return new SqlError($error->error, $error->getMessage(), $error, $following, $error->signalled, $error->getCode(), $error->recorded);
    }
}
