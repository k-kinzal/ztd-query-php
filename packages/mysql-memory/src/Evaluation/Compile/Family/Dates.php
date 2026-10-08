<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Dates as Parts;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles date arithmetic: the result is a DATE, DATETIME or TIME for an operand of that kind, else a string.
 *
 * A DATE moved by a unit smaller than a day becomes a DATETIME. Any other operand gives a
 * string of 29 characters, a date or a datetime as the value reached.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Dates
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles `expr + INTERVAL n unit` and `expr - INTERVAL n unit`.
     */
    public function arithmetic(IntervalArithmetic $node, Scope $scope): Evaluable
    {
        return $this->shift($node->operand, $node->interval->quantity, $node->interval->unit, $node->subtract, $scope, $node);
    }

    /**
     * Compiles `INTERVAL n unit + expr`.
     */
    public function addition(IntervalAddition $node, Scope $scope): Evaluable
    {
        return $this->shift($node->operand, $node->interval->quantity, $node->interval->unit, false, $scope, $node);
    }

    /**
     * Compiles DATE_ADD and DATE_SUB.
     */
    public function call(DateArithmetic $node, Scope $scope): Evaluable
    {
        return $this->shift($node->date, $node->quantity, $node->unit, $node->subtract, $scope, $node);
    }

    /**
     * Compiles a date moved by an interval.
     */
    public function shift(Scalar $operand, Scalar $quantity, IntervalUnit $unit, bool $subtract, Scope $scope, Scalar $node): Evaluable
    {
        $date = $this->compiler->compile($operand, $scope);
        $amount = $this->compiler->compile($quantity, $scope);

        return new DateShift($date, $amount, $unit, $subtract, $this->compiler->domain($node));
    }

    /**
     * Compiles EXTRACT(unit FROM value): the parts of the unit, written together as one integer.
     */
    public function extract(Extract $node, Scope $scope): Evaluable
    {
        $source = $this->compiler->compile($node->source, $scope);
        $unit = $node->unit;
        $domain = $this->compiler->domain($node);
        $parts = new Parts();

        return (new Texts($this->compiler))->call('EXTRACT', [$source], $domain, static fn (Frame $f, array $a): ?int => $parts->extract($f, $a[0], $unit));
    }
}
