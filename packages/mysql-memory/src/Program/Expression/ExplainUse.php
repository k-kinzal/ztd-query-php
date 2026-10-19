<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Resolution\Views;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write as W;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Operation;

/**
 * Prepares a retained explanation before checking its invocation-time target.
 *
 * The ordinary explanation supplies dependency facts without executing a query
 * or emitting a plan. The output target is retained on the original instruction
 * and checked only after those dependencies prepare.
 *
 * @visibility MySqlMemory
 */
final class ExplainUse
{
    /**
     * Returns the first dependency or output-target error without running the explained statement.
     */
    public function error(DeferredExplain $explanation, Session $session): SqlError
    {
        try {
            (new \MySqlMemory\Command\Explain\ExplainCommand())->check($explanation, $session);
        } catch (SqlError $error) {
            return $error;
        }
        $database = $explanation->database->value ?? $session->variables->database;
        Views::refreshAll($session->instance->dictionary, $session->settings());
        $previous = $session->variables->database;
        $session->variables->database = $database;
        try {
            if ($explanation->statement instanceof W\DeferredInsertRows || $explanation->statement instanceof W\DeferredInsertQuery) {
                (new InsertUse())->raise($explanation->statement, $session, false);
            }
            if ($explanation->statement instanceof W\DeferredUpdate) {
                (new \MySqlMemory\Program\Write\UpdateUse())->raise($explanation->statement, $session, false);
            }
            if ($explanation->statement instanceof W\DeferredDelete || $explanation->statement instanceof W\DeferredMultipleDelete) {
                (new DeleteUse())->raise($explanation->statement, $session, false);
            }
            if ($explanation->statement instanceof DeferredQuery) {
                (new QueryUse())->raise($explanation->statement, $session, false);
            }
            if ($explanation->statement instanceof DeferredSelection) {
                (new SelectionUse())->raise($explanation->statement, $session, false);
            }
            $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
            $inspection = new Explain($explanation->statement, $explanation->format, database: $explanation->database);
            (new Problems())->raise(new Operation($context, $inspection), $session);
        } catch (SqlError $error) {
            return $error;
        } finally {
            $session->variables->database = $previous;
        }
        (new \MySqlMemory\Command\Explain\ExplainCommand())->analysis($explanation, $session);
        \SqlSemantics\Diagnostic\Check::invariant($explanation->into !== null, 'A prepared ordinary explanation retains an invalid target.');
        return StatementError::IllegalUserVariable->error((new \MySqlMemory\Error\DiagnosticText())->bytes($explanation->into->name->value, 100));
    }

    /**
     * Raises the reached instruction's condition through the caller's handler machinery.
     *
     * @throws SqlError Always, after preparing the complete explanation
     */
    public function raise(DeferredExplain $explanation, Session $session): never
    {
        StatementCounters::query($session);
        StatementCounters::command($session, $explanation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Explainable ? (\MySqlMemory\Session\State\StatementKind::of($explanation->statement) ?? 'Com_select') : 'Com_select');
        $session->diagnostics->clear();
        throw $this->error($explanation, $session);
    }
}
