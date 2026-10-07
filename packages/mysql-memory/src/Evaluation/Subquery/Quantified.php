<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Comparator;
use MySqlMemory\Evaluation\Operator\Compare;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

/**
 * IN, ANY and ALL over a subquery.
 *
 * ANY (and IN, which is `= ANY`) is 1 when the comparison holds for a row, else NULL when it is
 * unknown for a row, else 0. ALL is 0 when the comparison fails for a row, else NULL when it is
 * unknown for one, else 1; ALL over no row is 1. NOT IN negates IN.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html.
 *
 * @visibility MySqlMemory
 */
final class Quantified implements Evaluable
{
    /**
     * @param Evaluable $operand The value compared
     * @param Rows $rows The rows of the subquery
     * @param ComparisonOperator $operator The comparison
     * @param bool $all Whether the comparison must hold for every row
     * @param bool $negated Whether the result is negated (NOT IN)
     * @param Comparator $comparator How the value compares with the column of the subquery
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(
        public readonly Evaluable $operand,
        public readonly Rows $rows,
        public readonly ComparisonOperator $operator,
        public readonly bool $all,
        public readonly bool $negated,
        public readonly Comparator $comparator,
        public readonly Domain $domain,
    ) {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Compares the value with the rows of the subquery.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $value = $this->operand->evaluate($frame);
        $iterator = $this->rows->start($frame);
        $unknown = false;
        $any = false;
        while (($row = $iterator->read()) !== null) {
            $any = true;
            $order = $this->comparator->compare($value, $row[0], $frame->context);
            $holds = $order === null ? null : Compare::holds($this->operator, $order);
            if ($holds === null) {
                $unknown = true;
            } elseif ($holds !== $this->all) {
                return $this->result(!$this->all);
            }
        }
        if (!$any) {
            return $this->result($this->all);
        }

        return $unknown ? null : $this->result($this->all);
    }

    /**
     * Answers a truth value, negated for NOT IN.
     */
    public function result(bool $truth): int
    {
        return $truth !== $this->negated ? 1 : 0;
    }
}
