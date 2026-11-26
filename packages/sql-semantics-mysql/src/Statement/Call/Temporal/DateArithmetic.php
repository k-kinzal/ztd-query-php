<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Temporal;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * A call of DATE_ADD(date, INTERVAL expr unit) or DATE_SUB(date, INTERVAL expr unit).
 *
 * Rule: MYSQL-DATE-ARITHMETIC-001. The server builds Item_date_add_interval.
 * ADDDATE and SUBDATE with an INTERVAL argument are the same functions
 * (synonyms in CallNoise) and are written as DATE_ADD and DATE_SUB. The
 * result type follows the date and the unit (ResultTyping::dateArithmetic);
 * the result is NULL when an operand is NULL or the date is invalid.
 * Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DateArithmetic implements Scalar
{
    use Snapshot;

    /**
     * @param bool $subtract Whether the interval is subtracted (DATE_SUB)
     * @param Scalar $date The date or time
     * @param Scalar $quantity The number of units
     * @param IntervalUnit $unit The unit
     */
    public function __construct(public readonly bool $subtract, public readonly Scalar $date, public readonly Scalar $quantity, public readonly IntervalUnit $unit)
    {
    }

    /**
     * Derives the operands and the type the date and the unit give.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $date = (new Arguments())->one($this->date, $derivation, $environment);
        (new Arguments())->one($this->quantity, $derivation, $environment);

        return new ScalarFact((new ResultTyping())->dateArithmetic($date->type, $this->unit), Nullability::Nullable);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->subtract ? 'DATE_SUB' : 'DATE_ADD')->glue()->symbol('(')->node($this->date)->symbol(',')
            ->keyword('INTERVAL')->node($this->quantity)->keyword($this->unit->value)->symbol(')');
    }
}
