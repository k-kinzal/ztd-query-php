<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression\Evaluation;

use LogicException;
use MySqlMemory\Program\Expression\RowUse;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Prepares immediate and stored DO from complete operands and their semantic bindings.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * Resolves dependencies before the fixed wildcard or scalar preparation condition.
     */
    public function check(Evaluation|DeferredEvaluation $statement, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): never
    {
        $this->database($statement, $facts, $session);
        foreach ($facts->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
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
        $planner = new \MySqlMemory\Plan\Planner($statement, $facts, $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $this->prepare($statement, $planner);
    }

    /**
     * Checks implicit databases in retained operand order, before name resolution.
     *
     * @throws \MySqlMemory\Error\SqlError When a reached dependency has no default database
     */
    public function database(Evaluation|DeferredEvaluation $statement, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
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
    public function prepare(Evaluation|DeferredEvaluation $statement, \MySqlMemory\Plan\Planner $planner): never
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
