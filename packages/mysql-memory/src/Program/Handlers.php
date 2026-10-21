<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Diagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;

/**
 * Finds and runs the handler of a condition a statement of a stored program raised.
 *
 * The handlers of the innermost block that has one for the condition are searched first, then
 * those of each block around it. Among the handlers of a block, one for the error number takes
 * precedence over one for the SQLSTATE, and that over SQLEXCEPTION, SQLWARNING and NOT FOUND. A
 * condition of the warning level is an SQLWARNING and an error an SQLEXCEPTION, unless its
 * SQLSTATE is of class '02', which is NOT FOUND. An error ends the statement and is handled at
 * once; the warnings of a statement that succeeds are handled once it ends, the handler for the
 * last of them winning between handlers of the same precedence. The handler runs with the
 * conditions as GET STACKED DIAGNOSTICS reads them. Handled conditions are discarded;
 * new conditions and explicit RESIGNAL publications survive the handler. Then
 * CONTINUE goes on after the statement while EXIT leaves the block that declares the handler.
 * An error no handler handles ends the program; a warning without handler is left in the
 * diagnostics area (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility MySqlMemory
 */
final class Handlers
{
    /**
     * @param Interpreter $interpreter The interpreter of the running program
     */
    public function __construct(public readonly Interpreter $interpreter)
    {
    }

    /**
     * Answers a SIGNAL or RESIGNAL of a declared condition as one of the SQLSTATE the condition stands for.
     *
     * @throws SqlError When no condition of the name is in scope, or it stands for an error number (ER_SIGNAL_BAD_CONDITION_TYPE)
     */
    public function named(Signal|Resignal $statement): Signal|Resignal
    {
        $name = $statement->condition instanceof ConditionName ? $statement->condition->name->value : '';
        $declared = null;
        foreach ($this->interpreter->activation->conditions as $condition) {
            $declared = strcasecmp($condition->name->value, $name) === 0 ? $condition : $declared;
        }
        if ($declared === null) {
            throw ProgramError::UndefinedCondition->error($name);
        }
        if (!$declared->value instanceof SqlState) {
            throw ProgramError::SignalConditionKind->error();
        }

        return $statement instanceof Signal ? new Signal($declared->value, $statement->items) : new Resignal($declared->value, $statement->items);
    }

    /**
     * Handles an error a step of the program raised: records it, runs its handler and answers where control goes; null goes on after the statement.
     *
     * @throws SqlError When no handler handles the error, which is then recorded
     */
    public function failed(SqlError $error): ?Jump
    {
        $error = (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $this->interpreter->session->settings()->release());
        $diagnostics = $this->interpreter->session->diagnostics;
        if (!$error->recorded) {
            $diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            foreach ($error->following as [$code, $message]) {
                $diagnostics->error($code, $message);
            }
        }
        $diagnostics->failure = $error;
        $handler = $this->find([[$error->getCode(), $error->sqlState(), 'Error']]);
        if ($handler === null) {
            throw $error->recorded ? $error : new SqlError($error->error, $error->getMessage(), $error, [], $error->signalled, $error->getCode(), true);
        }

        return $this->activate($handler);
    }

    /**
     * Handles the warnings the last step raised, when a handler handles one, and answers where control goes.
     *
     * @throws SqlError When the handler raises an error no handler handles
     */
    public function warned(): ?Jump
    {
        $diagnostics = $this->interpreter->session->diagnostics;
        if ($diagnostics->stale) {
            return null;
        }
        $conditions = [];
        foreach ($diagnostics->conditions as $position => [$level, $number]) {
            if ($level === 'Warning') {
                $conditions[] = [$number, $diagnostics->item($position, 'RETURNED_SQLSTATE'), $level];
            }
        }
        $handler = $conditions === [] ? null : $this->find($conditions);

        return $handler === null ? null : $this->activate($handler);
    }

    /**
     * Finds the handler for one of the conditions: of the innermost block, then of the highest precedence, then for the last condition.
     *
     * @param list<array{int, string, string}> $conditions The error number, SQLSTATE and level of each condition
     */
    public function find(array $conditions): ?Handler
    {
        $best = null;
        $rank = null;
        foreach ($conditions as $index => [$number, $state, $level]) {
            foreach ($this->interpreter->activation->handlers as $handler) {
                $precedence = $this->precedence($handler, $number, $state, $level);
                $candidate = [$handler->mark[1], $precedence, $index];
                if ($precedence > 0 && ($rank === null || $candidate >= $rank)) {
                    [$best, $rank] = [$handler, $candidate];
                }
            }
        }

        return $best;
    }

    /**
     * Answers how closely a handler matches a condition: 4 for its error number, 3 for its SQLSTATE, 2 for SQLEXCEPTION, 1 for SQLWARNING or NOT FOUND, 0 when it does not.
     */
    public function precedence(Handler $handler, int $number, string $state, string $level): int
    {
        $best = 0;
        foreach ($handler->declaration->conditions as $condition) {
            $best = max($best, $this->match($this->meaning($condition, $handler), $number, $state, $level));
        }

        return $best;
    }

    /**
     * Answers the value a condition name stands for, as declared where the handler is.
     */
    public function meaning(Condition $condition, Handler $handler): Condition
    {
        if (!$condition instanceof ConditionName) {
            return $condition;
        }
        $declared = array_slice($this->interpreter->activation->conditions, 0, $handler->mark[2]);
        for ($index = count($declared) - 1; $index >= 0; $index--) {
            if (strcasecmp($declared[$index]->name->value, $condition->name->value) === 0) {
                return $declared[$index]->value;
            }
        }

        return $condition;
    }

    /**
     * Answers how closely a condition value matches a condition, or 0 when it does not.
     */
    public function match(Condition $condition, int $number, string $state, string $level): int
    {
        $class = substr($state, 0, 2);

        return match (true) {
            $condition instanceof ErrorCode => $condition->number() === $number ? 4 : 0,
            $condition instanceof SqlState => $condition->state->value === $state ? 3 : 0,
            $condition instanceof GeneralCondition => match ($condition->class) {
                ConditionClass::NotFound => $class === '02' ? 1 : 0,
                ConditionClass::SqlWarning => $class !== '02' && $level !== 'Error' ? 1 : 0,
                ConditionClass::SqlException => $class !== '02' && $level === 'Error' ? 2 : 0,
            },
            default => 0,
        };
    }

    /**
     * Runs a handler in its block scope, removing handled conditions only when its area was not replaced.
     *
     * @throws SqlError When the handler raises an error no handler handles
     */
    public function activate(Handler $handler): ?Jump
    {
        $activation = $this->interpreter->activation;
        $session = $this->interpreter->session;
        if ($session->running->partial !== null) {
            $partial = $session->running->partial;
            (new Statements($session, $activation))->publish(new \MySqlMemory\Result\ResultSet($partial->columns, $partial->rows, $partial->warnings, moreResults: $partial->moreResults, interruptions: $partial->interruptions, binaryValues: $partial->binaryValues));
            $session->running->partial = null;
        }
        $saved = [$activation->scope, $activation->handlers, $activation->conditions, $activation->cursors];
        $activation->leave($handler->mark);
        $stacked = clone $session->diagnostics;
        $activation->stacked[] = $stacked;
        try {
            $jump = $this->interpreter->statement($handler->declaration->statement);
        } finally {
            array_pop($activation->stacked);
            [$activation->scope, $activation->handlers, $activation->conditions, $activation->cursors] = $saved;
        }
        if ($session->diagnostics->revision === $stacked->revision) {
            $session->diagnostics->clear();
        }
        if ($jump !== null) {
            return $jump;
        }

        return $handler->declaration->action === HandlerAction::Exit ? new Jump(Flow::Exit, null, $handler->block) : null;
    }
}
