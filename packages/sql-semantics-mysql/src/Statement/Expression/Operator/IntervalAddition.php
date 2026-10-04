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
 * Date arithmetic with a leading interval: `INTERVAL n unit + date` (`Item_date_add_interval`).
 *
 * It is a primary expression whose date operand extends to the right over
 * every operator stronger than AND, so `INTERVAL 1 DAY + a = b` adds the
 * interval to the comparison (MYSQL-PRECEDENCE-001). Only addition has this form.
 *
 * Rule: MYSQL-INTERVAL-ADDITION-001. Facts: those of
 * MYSQL-INTERVAL-ARITHMETIC-001. Terminates: the quantity and the operand
 * are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 * Status: Implemented.
 *
 * @visibility public
 * @example Adding an interval written first
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE INTERVAL 1 DAY + DATE '2024-01-31'");
 *     $query->facts->scalar($query->statement->where)->type->descriptor->name() // => 'DATE'
 */
final class IntervalAddition implements Scalar
{
    use Snapshot;

    /**
     * @param Interval $interval The interval added
     * @param Scalar $operand The date or time value
     */
    public function __construct(public readonly Interval $interval, public readonly Scalar $operand)
    {
        Check::input((new Precedence())->opening($operand) >= Precedence::NEGATION, 'The operand of a leading interval needs a grouping to keep its place.');
    }

    /**
     * Derives the quantity and the operand and the temporal type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operands->single($derivation->scalar($this->interval->quantity, $environment), $derivation);
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);

        return new ScalarFact((new TemporalResult())->interval($operand->type, $this->interval->unit, $derivation->context->profile->grammar), Nullability::Nullable);
    }

    /**
     * Writes the interval, the plus sign and the operand.
     */
    public function render(Output $out): void
    {
        $out->node($this->interval)->symbol('+')->node($this->operand);
    }
}
