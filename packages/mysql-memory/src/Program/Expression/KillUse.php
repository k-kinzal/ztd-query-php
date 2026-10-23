<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Utility\ProcessReferences;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;

/**
 * Prepares a reached non-scalar KILL operand from its complete semantic structure.
 *
 * Grammar conditions precede forbidden process dependencies. A later preparation
 * failure also records the constant-expression condition. Verified on MySQL 5.6,
 * 5.7 and 8.4, including unreachable stored instructions and missing dependencies.
 *
 * @visibility MySqlMemory
 */
final class KillUse
{
    /**
     * Answers the process operand's first SQL condition and its following condition.
     */
    public function error(RowOperand|DeferredOperand $process, Session $session): SqlError
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $derivation->scalar($process, $derivation->environment());
        foreach ($derivation->facts()->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                return (new Errors())->error($problem, $session);
            }
        }
        $dependencies = new Derivation($context);
        (new ProcessReferences())->check($process, $dependencies);
        foreach ($dependencies->facts()->diagnostics as $problem) {
            return (new Errors())->error($problem, $session);
        }
        try {
            (new RowUse())->raise($process, $session);
        } catch (SqlError $error) {
            return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), [...$error->following, [StatementError::SetConstantExpression->value, StatementError::SetConstantExpression->message()]], $error->signalled, $error->getCode(), $error->recorded);
        }
    }

    /**
     * Starts a stored KILL instruction and raises its preparation failure.
     *
     * @throws SqlError Always, after binding the retained dependencies
     */
    public function raise(RowOperand|DeferredOperand $process, Session $session): never
    {
        \MySqlMemory\Session\State\StatementCounters::query($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_kill');
        $session->diagnostics->clear();
        throw $this->error($process, $session);
    }
}
