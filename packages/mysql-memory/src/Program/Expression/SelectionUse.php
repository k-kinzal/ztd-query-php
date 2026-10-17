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
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares a stored selection from its semantic relations and operands.
 *
 * An ordinary SQL instruction starts a new diagnostics area on every release.
 * Its input is opened before projections bind; no row-valued projection is
 * converted into a substitute scalar or a reconstructed SQL query.
 *
 * @visibility MySqlMemory
 */
final class SelectionUse
{
    /**
     * Raises the first binding condition or the retained scalar-use width error.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after binding the reached operands
     */
    public function raise(DeferredSelection $selection, Session $session, bool $instruction = true): never
    {
        if ($instruction) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $selection->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $this->diagnosed($derivation->facts(), $session);
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($selection, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $scope = new \MySqlMemory\Evaluation\Scope();
        $width = $selection->from === null ? 0 : $planner->relations->plan($selection->from, $scope)->width();
        $scope->grouping = new \MySqlMemory\Plan\Grouping\Collected($planner, $width);
        $scope->windowing = new \MySqlMemory\Plan\Window\Collected($planner, $width);
        foreach ($selection->items as $item) {
            if ($item instanceof DeferredProjection) {
                (new RowUse())->prepare($item->expression, $planner->compiler, $scope);
                throw (new RowUse())->error($item->expression);
            }
            if ($item instanceof SelectExpression) {
                $planner->compiler->compile($item->expression, $scope);
            }
        }
        $this->clauses($selection, $planner, $scope);

    }
    /**
     * Reports grammar conditions before relation binding and operand preparation.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained diagnostic precedes preparation
     */
    public function diagnosed(\SqlSemantics\Statement\Fact\Facts $facts, Session $session): void
    {
        foreach ($facts->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier || ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::StarWithoutTables)) {
                throw (new Errors())->error($problem, $session);
            }
        }
    }

    /**
     * Prepares filters and sort items in their native order before any rows are read.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency or retained operand is refused
     * @throws LogicException When no retained operand raises its preparation condition
     */
    public function clauses(DeferredSelection $selection, \MySqlMemory\Plan\Planner $planner, \MySqlMemory\Evaluation\Scope $scope): never
    {
        $ungrouped = clone $scope;
        $ungrouped->grouping = null;
        $ungrouped->windowing = null;
        if ($selection->where !== null) {
            $this->operand($selection->where, $planner->compiler, $ungrouped->inClause('where clause'));
        }
        foreach ($selection->groupBy->items ?? [] as $item) {
            $this->operand($item->expression, $planner->compiler, $ungrouped->inClause('group statement'));
        }
        if ($selection->having !== null) {
            $having = $scope->inClause('having clause');
            $having->windowing = null;
            $this->operand($selection->having, $planner->compiler, $having);
        }
        foreach ($selection->orderBy as $item) {
            $this->operand($item->expression, $planner->compiler, $scope->inClause('order clause'));
        }
        throw new LogicException('A deferred selection must retain a preparation condition.');
    }

    /**
     * Resolves a scalar or ordinal, or raises a deferred use after preparing its dependencies.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency or retained operand is refused
     */
    public function operand(Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal $operand, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        if ($operand instanceof Scalar || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal) {
            $compiler->compile($operand, $scope);
            return;
        }
        (new RowUse())->prepare($operand, $compiler, $scope);
        throw (new RowUse())->error($operand);
    }

}
