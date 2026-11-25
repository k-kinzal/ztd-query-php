<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Emits SQL from a complete PostgreSQL SELECT.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '"__ztd_source"';

    /**
     * Renders the result columns in order, then the optional source and filter.
     */
    public function render(Select $select, bool $localNames = false, ?Destination $assignment = null): string
    {
        $source = $select->source;
        $items = [];
        foreach ($select->columns as $slot => $column) {
            $name = $localNames ? $this->localColumn($slot) : $column->name->quoted();
            $expression = $column->expression;
            $value = $assignment !== null && $expression instanceof NullLiteral
                ? 'CAST(NULL AS pg_catalog.' . $assignment->written[$slot]->kind->value . ')'
                : $this->expression($expression, $source);
            $items[] = $value . ' AS ' . $name;
        }
        $sql = ($select->distinct ? 'SELECT DISTINCT ' : 'SELECT ') . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . $this->from($source);
        }
        if ($select->where !== null) {
            $sql .= ' WHERE ' . $this->predicate($select->where, $source);
        }

        return $sql . (new ResultSql())->render($select, $this);
    }

    /**
     * Names one slot inside a derived projection.
     */
    public function localColumn(int $slot): string
    {
        return '"__ztd_c' . $slot . '"';
    }

    /**
     * Renders a checked expression occurrence.
     */
    public function expression(Arithmetic|BuiltinCast|CollatedText|ColumnReference|IntegerCase|IntegerLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|TruthTest|BooleanColumnReference|BooleanLiteral|NullLiteral|NullTest|Parameter|TextColumnReference|TextComparison|TextLiteral|WideIntegerLiteral|WideIntegerColumnReference $expression, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        return match (true) {
            $expression instanceof ColumnReference, $expression instanceof TextColumnReference, $expression instanceof BooleanColumnReference, $expression instanceof WideIntegerColumnReference => $this->column($expression, $source, $offset, $qualify),
            $expression instanceof Arithmetic => '(' . $this->expression($expression->left, $source, $offset, $qualify) . ' OPERATOR(pg_catalog.' . $expression->operator->value . ') ' . $this->expression($expression->right, $source, $offset, $qualify) . ')',
            $expression instanceof BuiltinCast => 'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS pg_catalog.' . $expression->target->value . ')',
            $expression instanceof IntegerCase => $this->integerCase($expression, $source, $offset, $qualify),
            $expression instanceof IntegerDistinctness => $this->integerDistinctness($expression, $source, $offset, $qualify),
            $expression instanceof IntegerMembership => $this->integerMembership($expression, $source, $offset, $qualify),
            $expression instanceof IntegerRange => $this->integerRange($expression, $source, $offset, $qualify),
            $expression instanceof NullTest, $expression instanceof IntegerComparison => $this->predicate($expression, $source, $offset, $qualify),
            $expression instanceof TruthTest => $this->truthTest($expression, $source, $offset, $qualify),
            $expression instanceof IntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int4)',
            $expression instanceof WideIntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int8)',
            $expression instanceof TextLiteral => $expression->toString(),
            $expression instanceof CollatedText => $this->collated($expression, $source, $offset, $qualify),
            $expression instanceof TextComparison => $this->textComparison($expression, $source, $offset, $qualify),
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => $expression->toString(),
            $expression instanceof BooleanLiteral => $expression->value ? 'TRUE' : 'FALSE',
        };
    }

    /**
     * Emits the native text operand with its exact explicit collation.
     */
    public function collated(CollatedText $expression, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $operand = $expression->operand instanceof NullLiteral ? 'CAST(NULL AS pg_catalog.text)'
            : $this->expression($expression->operand, $source, $offset, $qualify);

        return '(' . $operand . ' COLLATE pg_catalog."' . $expression->collation->value . '")';
    }

    /**
     * Emits both typed text values under the selected native collation.
     */
    public function textComparison(TextComparison $comparison, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $left = $this->collated(new CollatedText($comparison->left, $comparison->collation), $source, $offset, $qualify);
        $right = $this->expression($comparison->right, $source, $offset, $qualify);
        $operator = 'OPERATOR(pg_catalog.' . $comparison->operator->value . ')';

        return '(' . $left . ' ' . $operator . ' ' . $right . ')';
    }

    /**
     * Emits the exact assigned field in the physical or derived input occurrence.
     */
    public function column(ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference $expression, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        $inputs = $source instanceof Join ? $source->inputs() : [$source];
        $input = $inputs[$expression->occurrence] ?? null;
        if ($input instanceof DerivedSource) {
            Check::invariant(isset($input->definition->columns[$expression->slot]), 'A complete SELECT has this derived slot.');
            $name = $this->localColumn($expression->slot);
        } else {
            Check::invariant(isset($input->columns[$expression->slot]), 'A complete SELECT has this source slot.');
            $name = $input->columns[$expression->slot]->quoted();
        }

        return ($qualify ? $this->alias($offset + $expression->occurrence) . '.' : '') . $name;
    }

    /**
     * Emits the ordered decisions and one optional fallback in the resolved int4 domain.
     */
    public function integerCase(IntegerCase $expression, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $sql = '(CASE';
        foreach ($expression->arms as $arm) {
            $sql .= ' WHEN ' . $this->predicate($arm->condition, $source, $offset, $qualify)
                . ' THEN ' . $this->integerOrNull($arm->result, $source, $offset, $qualify);
        }
        if ($expression->otherwise !== null) {
            $sql .= ' ELSE ' . $this->integerOrNull($expression->otherwise, $source, $offset, $qualify);
        }

        return $sql . ' END)';
    }

    /**
     * Emits a value with the type required by a resolved int4 operand position.
     */
    public function integerOrNull(Arithmetic|ColumnReference|IntegerCase|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $value, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        return match (true) {
            $value instanceof NullLiteral => 'CAST(NULL AS pg_catalog.int4)',
            $value instanceof WideIntegerNull => 'CAST(NULL AS pg_catalog.int8)',
            $value instanceof Arithmetic, $value instanceof ColumnReference, $value instanceof IntegerCase,
            $value instanceof IntegerLiteral, $value instanceof WideIntegerColumnReference, $value instanceof WideIntegerLiteral
                => $this->expression($value, $source, $offset, $qualify),
        };
    }

    /**
     * Tests the typed operand against the selected truth value exactly once.
     */
    public function truthTest(TruthTest $test, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        return '(' . $this->predicate($test->operand, $source, $offset, $qualify) . ' ' . $test->operator->value . ')';
    }

    /**
     * Emits the qualified equality and defines the NULL result for both operand positions.
     */
    public function integerDistinctness(IntegerDistinctness $test, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $left = $this->integerOrNull($test->left, $source, $offset, $qualify);
        $right = $this->integerOrNull($test->right, $source, $offset, $qualify);
        $equality = '(' . $left . ' OPERATOR(pg_catalog.=) ' . $right . ')';
        $same = 'COALESCE(' . $equality . ', (' . $left . ' IS NULL AND ' . $right . ' IS NULL))';

        return $test->negated ? $same : '(NOT ' . $same . ')';
    }

    /**
     * Emits comparisons with the same builtin identity independently of search path.
     */
    public function integerRange(IntegerRange $range, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        $subject = $this->integerOrNull($range->subject, $source, $offset, $qualify);
        $lower = $this->integerOrNull($range->lower, $source, $offset, $qualify);
        $upper = $this->integerOrNull($range->upper, $source, $offset, $qualify);
        $format = '((%1$s OPERATOR(pg_catalog.>=) %2$s) AND (%1$s OPERATOR(pg_catalog.<=) %3$s))';
        $interval = sprintf($format, $subject, $lower, $upper);
        if ($range->symmetric) {
            $interval = '(' . $interval . ' OR ' . sprintf($format, $subject, $upper, $lower) . ')';
        }

        return $range->negated ? '(NOT ' . $interval . ')' : $interval;
    }

    /**
     * Emits one signed equality per alternative with the native three-valued ANY rule.
     */
    public function integerMembership(IntegerMembership $test, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $subject = $this->integerOrNull($test->subject, $source, $offset, $qualify);
        $alternatives = [];
        foreach ($test->alternatives as $alternative) {
            $alternatives[] = $this->integerOrNull($alternative, $source, $offset, $qualify);
        }
        $list = 'CAST(ARRAY[' . implode(', ', $alternatives) . '] AS pg_catalog.int8[])';
        $match = '(' . $subject . ' OPERATOR(pg_catalog.=) ANY (' . $list . '))';

        return $test->negated ? '(NOT ' . $match . ')' : $match;
    }

    /**
     * Emits a table or a fully parenthesized join tree, using its global alias offset.
     */
    public function from(TableSource|DerivedSource|Join $source, int $offset = 0): string
    {
        if ($source instanceof DerivedSource) {
            return '(' . $this->render($source->definition, true) . ') AS ' . $this->alias($offset);
        }
        if ($source instanceof TableSource) {
            return ($source->only ? 'ONLY ' : '') . $source->schema->quoted() . '.' . $source->table->quoted() . ' AS ' . $this->alias($offset);
        }
        $rightOffset = $offset + ($source->left instanceof Join ? count($source->left->inputs()) : 1);
        $sql = '(' . $this->from($source->left, $offset) . ' ' . $source->kind->value . ' JOIN ' . $this->from($source->right, $rightOffset);
        if ($source->on !== null) {
            $sql .= ' ON ' . $this->predicate($source->on, $source, $offset);
        }

        return $sql . ')';
    }

    /**
     * Emits the typed native predicate relative to the scope that owns it.
     */
    public function predicate(BooleanColumnReference|BooleanLiteral|ColumnReference|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|IntegerLiteral|LogicalPredicate|NullLiteral|NullTest|TextColumnReference|TextComparison|TruthTest|WideIntegerColumnReference $condition, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        if ($condition instanceof IntegerComparison) {
            $left = $this->expression($condition->left, $source, $offset, $qualify);
            $right = $this->expression($condition->right, $source, $offset, $qualify);
            $operator = 'OPERATOR(pg_catalog.' . $condition->operator->value . ')';

            return '(' . $left . ' ' . $operator . ' ' . $right . ')';
        }

        if ($condition instanceof LogicalPredicate) {
            $left = $this->predicate($condition->left, $source, $offset, $qualify);
            if ($condition->right === null) {
                return '(NOT ' . $left . ')';
            }

            return '(' . $left . ' ' . $condition->operator->value . ' ' . $this->predicate($condition->right, $source, $offset, $qualify) . ')';
        }
        if ($condition instanceof NullTest) {
            $operand = $this->predicate($condition->operand, $source, $offset, $qualify);

            return '(' . $operand . ($condition->negated ? ' IS NOT NULL)' : ' IS NULL)');
        }

        return $this->expression($condition, $source, $offset, $qualify);
    }

    /**
     * Names the corresponding table occurrence in the emitted statement.
     */
    public function alias(int $occurrence): string
    {
        return $occurrence === 0 ? self::SOURCE_ALIAS : '"__ztd_source_' . $occurrence . '"';
    }
}
