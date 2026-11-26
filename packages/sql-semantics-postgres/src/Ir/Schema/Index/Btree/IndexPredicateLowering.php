<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Resolves a partial B-tree condition to logical nodes, Boolean fields and column tests.
 *
 * @visibility SqlSemantics
 */
final class IndexPredicateLowering
{
    /**
     * Translates the optional parsed WHERE condition.
     *
     * @throws InvalidConstruction When the predicate does not use a physical column
     */
    public function lower(
        BoundStatement $operation,
        CreateIndex $statement,
    ): NullIndexPredicate|IndexTruthTest|IndexBooleanColumn|IndexBooleanPredicate|null {
        return $statement->where === null ? null : $this->node($operation, $statement, $statement->where);
    }

    /**
     * Preserves the parsed operator, grouping and ordered operands.
     * @throws InvalidConstruction When a terminal is outside the checked column roles
     */
    public function node(
        BoundStatement $operation,
        CreateIndex $statement,
        Scalar $condition,
    ): NullIndexPredicate|IndexTruthTest|IndexBooleanColumn|IndexBooleanPredicate {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition instanceof BooleanOperation) {
            $operator = match ($condition->operator) {
                BooleanOperator::And => LogicalOperator::And,
                BooleanOperator::Or => LogicalOperator::Or,
            };

            return new IndexBooleanPredicate($operator, $this->node($operation, $statement, $condition->left), $this->node($operation, $statement, $condition->right));
        }
        if ($condition instanceof Negation) {
            return new IndexBooleanPredicate(LogicalOperator::Not, $this->node($operation, $statement, $condition->operand));
        }
        if ($condition instanceof BooleanTest) {
            return new IndexTruthTest($this->slot($operation, $statement, $condition->operand), TruthOperator::from($condition->kind->value));
        }
        if ($condition instanceof ColumnReference) {
            return new IndexBooleanColumn($this->slot($operation, $statement, $condition));
        }
        Check::input($condition instanceof NullTest, 'A partial B-tree needs a direct scalar column NULL test.');

        return new NullIndexPredicate($this->slot($operation, $statement, $condition->operand), $condition->negated);
    }

    /**
     * Finds the tested column by the exact declared owner and occurrence.
     * @throws InvalidConstruction When the test does not use a physical column
     */
    public function slot(BoundStatement $operation, CreateIndex $statement, Scalar $operand): int
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        Check::input($operand instanceof ColumnReference, 'A partial B-tree needs a direct scalar column NULL test or Boolean truth test.');
        $table = $operation->facts->relation($statement)->table;
        $resolved = $operation->facts->scalar($operand)->resolution;
        Check::input($table instanceof DeclaredTable && $resolved instanceof ResolvedColumn
            && $resolved->depth === 0 && $resolved->relation === $statement, 'The partial B-tree operand needs an exact local column.');
        $column = $resolved->declaration();
        foreach ($table->table->columns as $slot => $candidate) {
            if ($candidate === $column) {
                return $slot;
            }
        }
        throw new InvalidConstruction('The partial B-tree operand must be a declared physical column.');
    }
}
