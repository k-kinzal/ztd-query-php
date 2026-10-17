<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Statement\Scalar;

/**
 * Checks the nested shape of compared operands without resolving names.
 *
 * Unknown subquery output is a catalog constraint. Written row widths and
 * explicit projections are intrinsic and cannot contradict one another.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RowShapes
{
    /**
     * Answers immediate fields, an empty list for one value, or null for an unknown width.
     * A null field denotes a scalar output column of a subquery.
     *
     * @return list<Scalar|RowOperand|null>|null
     */
    public function parts(Scalar|RowOperand|null $operand): ?array
    {
        while ($operand instanceof Grouped || $operand instanceof GroupedRow) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Row) {
            return $operand->elements;
        }
        if ($operand instanceof ScalarSubquery || $operand instanceof RowSubquery) {
            $width = (new QueryWidth())->of($operand->query);
            return $width === null ? null : ($width === 1 ? [] : array_fill(0, $width, null));
        }
        return [];
    }

    /**
     * Finds the first incompatible position, including nested row fields.
     */
    public function problem(Scalar|RowOperand|null $left, Scalar|RowOperand|null $right): ?OperandColumns
    {
        return $this->pairs($this->parts($left), $this->parts($right));
    }

    /**
     * Compares an operand with a query whose width may depend on declarations.
     */
    public function query(Scalar|RowOperand $operand, \SqlSemantics\Statement\Query $query): ?OperandColumns
    {
        $width = (new QueryWidth())->of($query);
        return $this->pairs($this->parts($operand), $width === null ? null : ($width === 1 ? [] : array_fill(0, $width, null)));
    }

    /**
     * Checks corresponding immediate fields.
     *
     * @param list<Scalar|RowOperand|null>|null $one
     * @param list<Scalar|RowOperand|null>|null $two
     */
    public function pairs(?array $one, ?array $two): ?OperandColumns
    {
        if ($one === null || $two === null) {
            return null;
        }
        if (max(1, count($one)) !== max(1, count($two))) {
            return new OperandColumns(max(1, count($one)), max(1, count($two)));
        }
        foreach ($one as $index => $element) {
            $problem = $this->problem($element, $two[$index]);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }
}
