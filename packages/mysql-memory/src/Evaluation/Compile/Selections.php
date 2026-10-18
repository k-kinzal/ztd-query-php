<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use LogicException;
use MySqlMemory\Program\Expression\RowUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles retained selection operands using an already resolved query scope.
 *
 * CTE planning and stored instruction invocation share this dependency order.
 * No source text, parser tree, catalog rebinding or replacement expression is used.
 *
 * @visibility MySqlMemory
 */
final class Selections
{
    /**
     * Prepares retained clauses with their already bound facts and lexical table definitions.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after preparing the reached operands
     */
    public function bound(DeferredSelection $selection, \MySqlMemory\Plan\Planner $planner, ?\MySqlMemory\Evaluation\Scope $outer = null): never
    {
        if ($selection->items[0] instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star && ($selection->from === null || $selection->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual)) {
            throw \MySqlMemory\Error\Family\QueryError::NoTablesUsed->error();
        }
        foreach ($selection->items as $item) {
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard && $item->table->name->value === '') {
                throw \MySqlMemory\Error\Family\QueryError::NoTablesUsed->error();
            }
        }
        $scope = new \MySqlMemory\Evaluation\Scope($outer);
        $width = $selection->from === null ? 0 : $planner->relations->plan($selection->from, $scope)->width();
        $scope->grouping = new \MySqlMemory\Plan\Grouping\Collected($planner, $width);
        $scope->windowNames = array_column($selection->windows, 'name');
        $scope->windowing = new \MySqlMemory\Plan\Window\Collected($planner, $width, $selection->windows);
        foreach ($selection->items as $item) {
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
        $this->clauses($selection, $planner, $scope);

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
        $scope->windowing?->finish($scope);
        throw new LogicException('A deferred selection must retain a preparation condition.');
    }

    /**
     * Resolves a scalar or ordinal, or raises a deferred use after preparing its dependencies.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency or retained operand is refused
     */
    public function operand(Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal $operand, Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        if ($operand instanceof Scalar || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal) {
            $compiler->compile($operand, $scope);
            return;
        }
        (new RowUse())->prepare($operand, $compiler, $scope);
        if (!(new RowUse())->pending($operand, $scope)) {
            throw (new RowUse())->error($operand);
        }
    }

    /**
     * Prepares set operands in query order before their output types are required.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained query operand cannot prepare
     */
    public function set(\SqlSemantics\Statement\Query $query, \MySqlMemory\Plan\Planner $planner, ?\MySqlMemory\Evaluation\Scope $outer): void
    {
        if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery) {
            $this->set($query->query, $planner, $outer);
        } elseif ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation && $query->left instanceof \SqlSemantics\Statement\Query) {
            $this->set($query->left, $planner, $outer);
            $this->set($query->right, $planner, $outer);
        } else {
            $planner->query($query, $outer);
        }
    }

}
