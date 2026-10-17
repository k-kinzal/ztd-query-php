<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Event;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth;

/**
 * Reports the conversion conditions following a refused legacy schedule operand.
 *
 * Observed on MySQL 5.6.51 and 5.7.44. Modern releases stop at the width error.
 * The input boundary supplies the clause and row role as semantic values.
 *
 * @visibility MySqlMemory\Session\Parse
 */
final class OperandConditions
{
    /**
     * Keeps the width error first, followed by the clause's failed conversion.
     */
    public function error(OperandWidth $problem, bool $legacy): SqlError
    {
        $first = QueryError::OperandColumns->error(1);
        if (!$legacy) {
            return $first;
        }
        $following = [];
        if ($problem->rowValue && in_array($problem->clause, [Clause::At, Clause::Starts], true)) {
            $following[] = [$first->getCode(), $first->getMessage()];
        }
        $conversion = match (true) {
            $problem->clause === Clause::Ends => ProgramError::EndsBeforeStarts->error(),
            $problem->clause === Clause::Interval && $problem->rowValue => ProgramError::IntervalNotPositive->error(),
            default => DataError::WrongValue->error($problem->clause->value, 'NULL'),
        };
        $following[] = [$conversion->getCode(), $conversion->getMessage()];
        return new SqlError($first->error, $first->getMessage(), following: $following);
    }
}
