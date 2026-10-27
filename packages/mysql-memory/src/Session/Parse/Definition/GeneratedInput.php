<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;

/**
 * Maps a typed generation-expression refusal without reconstructing its source expression.
 *
 * @visibility MySqlMemory
 */
final class GeneratedInput
{
    /**
     * Formats the native release's definition-time condition.
     */
    public function error(RefusedOperand $problem, Session $session): SqlError
    {
        if ($problem->scope !== null && $problem->scope->schema === null && $session->variables->database === '') {
            return \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
        if ($problem->expression !== null) {
            $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context(session: $session->resolution()));
            $pending = [\SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($problem->expression)];
            while ($pending !== []) {
                $node = array_pop($pending);
                if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
                    $derivation->scalar($node, $derivation->environment());
                }
                array_push($pending, ...array_reverse((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Operands())->children($node)));
            }
            foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $dependency) {
                if (\MySqlMemory\Session\Problem\Stages::parsed($dependency) || \MySqlMemory\Session\Problem\Stages::characters($dependency, false)) {
                    return (new \MySqlMemory\Session\Problem\Errors())->error($dependency, $session);
                }
            }
        }
        if ($session->settings()->legacy()) {
            return ConstraintError::GeneratedSubquery->error($problem->column->value);
        }
        if (in_array($problem->kind, [OperandKind::FunctionCall, OperandKind::StoredFunction], true)) {
            \SqlSemantics\Diagnostic\Check::invariant($problem->function !== null, 'A function refusal retains its diagnostic name.');
            return ConstraintError::GeneratedFunction->error($problem->column->value, $problem->function);
        }
        return match ($problem->kind) {
            OperandKind::Row => ConstraintError::GeneratedRow->error($problem->column->value),
            OperandKind::Subquery, OperandKind::Parameter => ConstraintError::GeneratedSubquery->error($problem->column->value),
            OperandKind::Variable => ConstraintError::DefaultVariable->error($problem->column->value),
        };
    }
}
