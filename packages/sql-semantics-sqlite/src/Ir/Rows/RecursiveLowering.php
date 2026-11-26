<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable as Definition;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Lowers a recursive arm only after resolving its sole input to the intended local definition.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RecursiveLowering
{
    /**
     * Binds the anchor and successor operands in their own scopes.
     * @param non-empty-list<Identifier> $names The ordered slots of the recursive definition
     */
    public function lower(BoundStatement $operation, Definition $definition, Compound $compound, array $names): RecursiveDefinition
    {
        Check::input(count($compound->steps) === 1 && $compound->orderBy === [] && $compound->limit === null, 'The recursive definition requires one step without queue ordering or bounds.');
        $part = $compound->steps[0];
        Check::input($part->operator === CompoundOperator::Union || $part->operator === CompoundOperator::UnionAll, 'The recursive definition requires UNION or UNION ALL.');
        $step = $part->query;
        Check::input($step instanceof Select && $step->from instanceof TableInput && $step->from->index === null, 'The recursive step requires a single self input.');
        $reference = $operation->facts->relation($step->from)->table;
        Check::input($reference instanceof CommonTable && $reference->definition === $definition, 'The recursive input must resolve to this local definition.');
        $binding = new SourceBinding($operation, $step->from, $names);
        $expressions = new ExpressionLowering();
        $successors = [];
        foreach ($this->operands($step) as $operand) {
            $successors[] = new RecursiveColumn($expressions->lower($operand, $binding));
        }
        $where = $step->where === null ? null : $expressions->lower($step->where, $binding);

        return new RecursiveDefinition($this->anchor($operation, $compound->first), $successors, $where, $part->operator === CompoundOperator::UnionAll);
    }

    /**
     * Converts independent anchor operands to checked rows without a recursive scope.
     */
    public function anchor(BoundStatement $operation, ValuesClause|Select $query): ValuesQuery
    {
        if ($query instanceof ValuesClause) {
            return (new ValuesLowering())->lower($operation, $query);
        }
        Check::input($query->from === null && $query->where === null, 'The recursive SELECT anchor requires independent expressions without a filter.');
        $scope = new SourceBinding($operation, null);
        $expressions = new ExpressionLowering();
        $values = [];
        foreach ($this->operands($query) as $operand) {
            $values[] = $expressions->lower($operand, $scope);
        }

        return new ValuesQuery([new ValueRow($values)]);
    }

    /**
     * Collects explicit expressions without grouping, windows or an arm-level result transform.
     * @return non-empty-list<Scalar>
     */
    public function operands(Select $query): array
    {
        Check::input(
            $query->quantifier !== SetQuantifier::Distinct && $query->groupBy === [] && $query->having === null
                && $query->windows === [] && $query->orderBy === [] && $query->limit === null,
            'A recursive definition arm requires a plain scalar projection.',
        );
        $operands = [];
        foreach ($query->columns as $column) {
            Check::input($column instanceof ResultColumn, 'A recursive definition arm requires explicit expressions.');
            $operands[] = $column->expression;
        }

        return $operands;
    }
}
