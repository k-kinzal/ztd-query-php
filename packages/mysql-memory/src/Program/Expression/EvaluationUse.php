<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
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
    public function raise(DeferredEvaluation $statement, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_do');
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($statement);
        $derivation = new Derivation($context);
        $statement->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $this->database($statement, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($statement, $session);
        if ($statement->problem() instanceof EvaluationWildcard) {
            throw (new Errors())->error($statement->problem(), $session);
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($statement, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $this->prepare($statement, $planner);
    }

    /**
     * Checks implicit databases in retained operand order, before name resolution.
     *
     * @throws \MySqlMemory\Error\SqlError When a reached dependency has no default database
     */
    public function database(DeferredEvaluation $statement, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
    {
        if ($session->variables->database !== '') {
            return;
        }
        $missing = [];
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable && $problem->name->schema === null) {
                $missing[spl_object_id($problem->name)] = $problem;
            }
        }
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($statement) as $node) {
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference && isset($missing[spl_object_id($node->name)])) {
                throw (new Errors())->error($missing[spl_object_id($node->name)], $session);
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall && $node->schema === null && !$node->native($session->settings()->release())) {
                throw (new Errors())->routine($node, $session);
            }
        }
    }

    /**
     * Binds every DO item before finishing its window preparation conditions.
     *
     * @throws \MySqlMemory\Error\SqlError When a reached operand cannot prepare
     * @throws LogicException When no retained condition refuses preparation
     */
    public function prepare(DeferredEvaluation $statement, \MySqlMemory\Plan\Planner $planner): never
    {
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->windowing = new \MySqlMemory\Plan\Window\Collected($planner, 0);
        foreach ($statement->items as $item) {
            if ($item instanceof DeferredProjection) {
                (new RowUse())->prepare($item->expression, $planner->compiler, $scope);
                if (!(new RowUse())->pending($item->expression, $scope)) {
                    throw (new RowUse())->error($item->expression);
                }
            }
            if ($item instanceof SelectExpression) {
                $planner->compiler->compile($item->expression, $scope);
            }
        }
        $scope->windowing->finish($scope);
        throw new LogicException('A deferred evaluation retains a preparation condition.');
    }
}
