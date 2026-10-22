<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Resolves schedule variables and table dependencies before event-specific expression restrictions.
 * CTE references use their lexical binding; only an unqualified base table needs
 * the current database. Event bodies retain their separate invocation scope.
 *
 * @visibility MySqlMemory
 */
final class EventSchedule
{
    /**
     * Checks only the schedule's operands, keeping the event body in its invocation scope.
     */
    public function statement(\SqlSemantics\Statement\Node $statement, Session $session): void
    {
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent) {
            return;
        }
        $schedule = $statement->schedule;
        $operands = $schedule instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule ? [$schedule->at] : ($schedule instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule ? array_values(array_filter([$schedule->quantity, $schedule->starts, $schedule->ends])) : []);
        $this->check($operands, $session);
    }

    /**
     * @param list<Operand> $operands Complete schedule expressions, excluding the event body
     * @throws SqlError When a reached variable or table cannot be resolved
     */
    public function check(array $operands, Session $session): void
    {
        $derivation = new Derivation($session->semantics()->context([], session: $session->resolution()));
        foreach ($operands as $operand) {
            $derivation->scalar($operand, $derivation->environment());
        }
        $facts = $derivation->facts();
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->inputs($operands) as $node) {
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
                $variable = new Derivation($derivation->context);
                $variable->scalar($node, $variable->environment());
                foreach ($variable->facts()->diagnostics as $problem) {
                    if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable || (!$session->settings()->legacy() && $problem instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse)) {
                        throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
                    }
                }
            }
            $name = $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference || $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable ? $node->name() : null;
            if ($name !== null && $name->schema === null && $session->variables->database === '') {
                foreach ($facts->diagnostics as $problem) {
                    if ($problem instanceof MissingTable && $problem->name === $name) {
                        throw QueryError::NoDatabase->error();
                    }
                }
            }
        }
    }
}
