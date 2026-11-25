<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Emits SQL from a complete MySQL SELECT.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '`__ztd_source`';

    /**
     * Renders the result columns in order, then the optional source and filter.
     */
    public function render(Select $select, bool $localNames = false): string
    {
        $source = $select->source;
        $items = [];
        foreach ($select->columns as $slot => $column) {
            $name = $localNames ? $this->localColumn($slot) : $column->name->quoted();
            $label = $column->name->value;
            $alias = $localNames || $label === ltrim($label, " \t\r\n\v\f") ? ' AS ' . $name : '';
            $items[] = $this->expression($column->expression, $source) . $alias;
        }
        $sql = ($select->distinct ? 'SELECT DISTINCT ' : 'SELECT ') . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . $this->from($source);
        } elseif ($select->release === GrammarRelease::MySql5651 && $select->orderBy !== []) {
            $sql .= ' FROM (SELECT 1 AS `__ztd_unit`) AS `__ztd_single`';
        } elseif (($select->release === GrammarRelease::MySql5651 || $select->release === GrammarRelease::MySql5744)
            && ($select->where !== null || $select->orderBy !== [] || $select->limit !== null)) {
            $sql .= ' FROM DUAL';
        }
        if ($select->where !== null) {
            $sql .= ' WHERE ' . $this->predicate($select->where, $source);
        }

        return $sql . (new ResultSql())->render($select);
    }

    /**
     * Names one slot inside a derived projection.
     */
    public function localColumn(int $slot): string
    {
        return '`__ztd_c' . $slot . '`';
    }

    /**
     * Renders a checked expression occurrence.
     */
    public function expression(Arithmetic|CollatedText|ColumnReference|IntegerLiteral|IntegerMembership|IntegerRange|BooleanLiteral|NullLiteral|NullTest|Parameter|SignedCase|SignedCast|TextColumnReference|TextComparison|TextLiteral|UnsignedCast|RangeIntegerLiteral $expression, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        return match (true) {
            $expression instanceof ColumnReference, $expression instanceof TextColumnReference => $this->column($expression, $source, $offset, $qualify),
            $expression instanceof Arithmetic => '(' . $this->expression($expression->left, $source, $offset, $qualify) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $offset, $qualify) . ')',
            $expression instanceof SignedCase => $this->signedCase($expression, $source, $offset, $qualify),
            $expression instanceof SignedCast => 'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS SIGNED)',
            $expression instanceof UnsignedCast => 'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS UNSIGNED)',
            $expression instanceof IntegerMembership => $this->integerMembership($expression, $source, $offset, $qualify),
            $expression instanceof IntegerRange => $this->integerRange($expression, $source, $offset, $qualify),
            $expression instanceof NullTest => $this->predicate($expression, $source, $offset, $qualify),
            $expression instanceof TextComparison => $this->textComparison($expression, $source, $offset, $qualify),
            $expression instanceof CollatedText => '(' . $this->expression($expression->operand, $source, $offset, $qualify)
                . ' COLLATE ' . $expression->collation->value . ')',
            $expression instanceof IntegerLiteral, $expression instanceof RangeIntegerLiteral, $expression instanceof TextLiteral => $expression->toString(),
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => $expression->toString(),
            $expression instanceof BooleanLiteral => $expression->value ? 'TRUE' : 'FALSE',
        };
    }

    /**
     * Emits both text operands under their resolved native comparison collation.
     */
    public function textComparison(TextComparison $comparison, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $left = $this->expression($comparison->left, $source, $offset, $qualify);
        $right = $this->expression($comparison->right, $source, $offset, $qualify);

        return '(' . $left . ' ' . $comparison->operator->value . ' ' . $right . ')';
    }

    /**
     * Emits the exact field name and its owning physical or derived occurrence.
     */
    public function column(ColumnReference|IntegralColumnReference|TextColumnReference $expression, TableSource|Join|null $source, int $offset, bool $qualify): string
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
     * Emits the native signed comparison without coercing or reordering the operands.
     */
    public function integerRange(IntegerRange $range, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        $subject = $this->rangeOperand($range->subject, $source, $offset, $qualify);
        $lower = $this->rangeOperand($range->lower, $source, $offset, $qualify);
        $upper = $this->rangeOperand($range->upper, $source, $offset, $qualify);

        return '(' . $subject . ($range->negated ? ' NOT BETWEEN ' : ' BETWEEN ') . $lower . ' AND ' . $upper . ')';
    }

    /**
     * Emits the native decision form and every ordered branch.
     */
    public function signedCase(SignedCase $choice, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $sql = '(CASE';
        if ($choice->subject !== null) {
            $sql .= ' ' . $this->caseOperand($choice->subject, $source, $offset, $qualify);
        }
        foreach ($choice->arms as $arm) {
            $sql .= ' WHEN ' . $this->caseOperand($arm->decision, $source, $offset, $qualify)
                . ' THEN ' . $this->caseOperand($arm->result, $source, $offset, $qualify);
        }
        if ($choice->otherwise !== null) {
            $sql .= ' ELSE ' . $this->caseOperand($choice->otherwise, $source, $offset, $qualify);
        }

        return $sql . ' END)';
    }

    /**
     * Emits a checked signed decision or result from the same occurrence.
     */
    public function caseOperand(
        BooleanLiteral|ColumnReference|IntegralColumnReference|IntegerMembership|NullLiteral|RangeIntegerLiteral|SignedCase $value,
        TableSource|Join|null $source,
        int $offset,
        bool $qualify,
    ): string {
        return match (true) {
            $value instanceof ColumnReference, $value instanceof IntegralColumnReference => $this->column($value, $source, $offset, $qualify),
            $value instanceof BooleanLiteral, $value instanceof NullLiteral, $value instanceof RangeIntegerLiteral,
            $value instanceof IntegerMembership, $value instanceof SignedCase => $this->expression($value, $source, $offset, $qualify),
        };
    }

    /**
     * Emits native IN with its complete signed operand list in order.
     */
    public function integerMembership(IntegerMembership $test, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        $subject = $this->rangeOperand($test->subject, $source, $offset, $qualify);
        $alternatives = [];
        foreach ($test->alternatives as $alternative) {
            $alternatives[] = $this->rangeOperand($alternative, $source, $offset, $qualify);
        }

        return '(' . $subject . ($test->negated ? ' NOT IN (' : ' IN (') . implode(', ', $alternatives) . '))';
    }

    /**
     * Renders a pure signed operand or its resolved reference.
     */
    public function rangeOperand(ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral $operand, TableSource|Join|null $source, int $offset, bool $qualify): string
    {
        return match (true) {
            $operand instanceof RangeIntegerLiteral => $operand->toString(),
            $operand instanceof ColumnReference, $operand instanceof IntegralColumnReference => $this->column($operand, $source, $offset, $qualify),
            $operand instanceof NullLiteral => $this->expression($operand, $source, $offset, $qualify),
        };
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
            return $source->schema->quoted() . '.' . $source->table->quoted() . ' AS ' . $this->alias($offset);
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
    public function predicate(BooleanLiteral|ColumnReference|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|IntegerLiteral|LogicalPredicate|NullLiteral|NullTest|TextColumnReference $condition, TableSource|Join|null $source, int $offset = 0, bool $qualify = true): string
    {
        if ($condition instanceof IntegerComparison) {
            $left = $this->expression($condition->left, $source, $offset, $qualify);
            $right = $this->expression($condition->right, $source, $offset, $qualify);
            $operator = $condition->operator->value;

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
        return $occurrence === 0 ? self::SOURCE_ALIAS : '`__ztd_source_' . $occurrence . '`';
    }
}
