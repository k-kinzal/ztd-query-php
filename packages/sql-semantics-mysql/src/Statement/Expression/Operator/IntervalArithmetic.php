<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\TemporalResult;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Date arithmetic with a trailing interval: `date + INTERVAL n unit` or `date - INTERVAL n unit` (`Item_date_add_interval`).
 *
 * It belongs to the additive level of bit_expr and associates to the left
 * with `+` and `-` (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-INTERVAL-ARITHMETIC-001. Facts: the type follows
 * MYSQL-TEMPORAL-RESULT-001; the result is NULL for an invalid date, so it
 * can always be NULL. Terminates: the operand and the quantity are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add.
 * Status: Implemented.
 *
 * @visibility public
 * @example Subtracting an interval
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE DATE '2024-01-31' - INTERVAL 1 MONTH");
 *     [$query->statement->where->subtract, $query->facts->scalar($query->statement->where)->type->descriptor->name()] // => [true, 'DATE']
 */
final class IntervalArithmetic implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The date or time value
     * @param Interval $interval The interval added or subtracted
     * @param bool $subtract Whether the interval is subtracted
     */
    public function __construct(public readonly Scalar $operand, public readonly Interval $interval, public readonly bool $subtract = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::ADDITIVE, Precedence::ADDITIVE), 'The operand of interval arithmetic needs a grouping to keep its place.');
    }

    /**
     * Derives the operand and the quantity and the temporal type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $operands->single($derivation->scalar($this->interval->quantity, $environment), $derivation);

        return new ScalarFact((new TemporalResult())->interval($operand->type, $this->interval->unit, $derivation->context->profile->grammar), Nullability::Nullable);
    }

    /**
     * Writes the operand, the sign and the interval.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->symbol($this->subtract ? '-' : '+')->node($this->interval);
    }
}
