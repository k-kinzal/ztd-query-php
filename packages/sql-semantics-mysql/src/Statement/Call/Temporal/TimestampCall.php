<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Temporal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of TIMESTAMPADD(unit, interval, datetime) or TIMESTAMPDIFF(unit, datetime1, datetime2).
 *
 * Rule: MYSQL-TIMESTAMP-CALL-001. TIMESTAMPADD adds a number of units to
 * its second operand and is typed as a date plus an interval; TIMESTAMPDIFF
 * is the integer number of units from the first to the second operand. The
 * units are the simple units only (interval_time_stamp). The result is NULL
 * when an operand is NULL or invalid. Terminates: the operands are strict
 * parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_timestampadd,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_timestampdiff.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing TIMESTAMPDIFF()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT TIMESTAMPDIFF(MONTH, '2024-01-01', '2024-03-01')");
 *     $query->field(0)->type->descriptor->name() // => 'BIGINT'
 */
final class TimestampCall implements Scalar
{
    use Snapshot;

    /**
     * @param TimestampOperation $operation The function
     * @param IntervalUnit $unit The unit
     * @param Scalar $first The number of units to add, or the start
     * @param Scalar $second The date or time to add to, or the end
     */
    public function __construct(public readonly TimestampOperation $operation, public readonly IntervalUnit $unit, public readonly Scalar $first, public readonly Scalar $second)
    {
    }

    /**
     * Derives the operands and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new Arguments())->one($this->first, $derivation, $environment);
        $second = (new Arguments())->one($this->second, $derivation, $environment);
        $type = $this->operation === TimestampOperation::Add ? (new ResultTyping())->dateArithmetic($second->type, $this->unit) : new Known(TypeClass::Integer->descriptor());

        return new ScalarFact($type, Nullability::Nullable);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->operation->value)->glue()->symbol('(')->keyword($this->unit->value)->symbol(',')->node($this->first)->symbol(',')->node($this->second)->symbol(')');
    }
}
