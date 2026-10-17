<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Prepares complete stored DO operands when execution reaches the instruction.
 *
 * Wildcard expansion and operand dependencies remain separate. No operand is
 * evaluated before preparation determines that each item has a scalar role.
 *
 * @visibility MySqlMemory
 */
final class EvaluationUse
{
    /**
     * Raises the first preparation condition of the retained items.
     *
     * @throws \MySqlMemory\Error\SqlError When expansion or operand preparation is refused
     * @throws LogicException When a deferred instruction has no preparation condition
     */
    public function raise(DeferredEvaluation $statement, Session $session): never
    {
        \MySqlMemory\Session\State\StatementCounters::query($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_do');
        $session->diagnostics->clear();
        if ($statement->problem() instanceof EvaluationWildcard) {
            throw (new Errors())->error($statement->problem(), $session);
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $statement->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($statement, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $scope = new \MySqlMemory\Evaluation\Scope();
        foreach ($statement->items as $item) {
            if ($item instanceof DeferredProjection) {
                (new RowUse())->prepare($item->expression, $planner->compiler, $scope);
                throw (new RowUse())->error($item->expression);
            }
            if ($item instanceof SelectExpression) {
                $planner->compiler->compile($item->expression, $scope);
            }
        }
        throw new LogicException('A deferred evaluation retains a preparation condition.');
    }
}
