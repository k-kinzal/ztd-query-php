<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto;

/**
 * Formats typed EXPLAIN option refusals without consulting input text.
 *
 * @visibility MySqlMemory
 */
final class ExplainInput
{
    /**
     * Preserves the option refusal selected by input lowering.
     */
    public function error(RefusedConnectionExplain|RefusedExplainInto|\SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget $problem): SqlError
    {
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget) {
            return StatementError::IllegalUserVariable->error((new \MySqlMemory\Error\DiagnosticText())->bytes($problem->target->name->value, 100));
        }
        if ($problem instanceof RefusedConnectionExplain) {
            return $problem->analyze ? StatementError::NotSupportedYet->error('EXPLAIN ANALYZE FOR CONNECTION') : StatementError::ExplainIntoForConnection->error();
        }
        if ($problem->analyze) {
            return StatementError::ExplainAnalyzeInto->error();
        }
        return $problem->format === null ? StatementError::ExplainIntoImplicitFormat->error() : StatementError::ExplainIntoFormat->error(strtoupper($problem->format->value));
    }
    /**
     * Resolves an explanation's database before its retained DELETE namespace.
     */
    public function targets(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets $problem, \MySqlMemory\Session\Session $session): SqlError
    {
        $option = (new \SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts())->options($session->settings()->release(), $problem->format, $problem->analyze, $problem->into);
        if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat) {
            return StatementError::UnknownExplainFormat->error($option->name->value);
        }
        if ($option !== null) {
            return $this->error($option);
        }
        if ($problem->database !== null && $session->instance->dictionary->schema($problem->database->value) === null) {
            return \MySqlMemory\Error\Family\QueryError::BadDatabase->error($problem->database->value);
        }
        $previous = $session->variables->database;
        $session->variables->database = $problem->database->value ?? $previous;
        try {
            return (new Dml\Targets())->unmatched($problem->targets, $session);
        } finally {
            $session->variables->database = $previous;
        }
    }
}
