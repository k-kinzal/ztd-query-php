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
        if ($session->settings()->legacy()) {
            return ConstraintError::GeneratedSubquery->error($problem->column->value);
        }
        if (in_array($problem->kind, [OperandKind::FunctionCall, OperandKind::StoredFunction], true)) {
            \SqlSemantics\Diagnostic\Check::invariant($problem->function !== null, 'A function refusal retains its diagnostic name.');
            return ConstraintError::GeneratedFunction->error($problem->column->value, $problem->function);
        }
        return match ($problem->kind) {
            OperandKind::Row => ConstraintError::GeneratedRow->error($problem->column->value),
            OperandKind::Subquery => ConstraintError::GeneratedSubquery->error($problem->column->value),
            OperandKind::Variable => ConstraintError::DefaultVariable->error($problem->column->value),
        };
    }
}
