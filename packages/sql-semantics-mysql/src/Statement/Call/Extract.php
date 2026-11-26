<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * A call of EXTRACT(unit FROM date): the part of a date or time a unit names.
 *
 * Rule: MYSQL-EXTRACT-CALL-001. The result is an integer; it is NULL when
 * the argument is NULL or not a valid date or time. Terminates: the operand
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_extract.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Extract implements Scalar
{
    use Snapshot;

    /**
     * @param IntervalUnit $unit The part to extract
     * @param Scalar $source The date or time
     */
    public function __construct(public readonly IntervalUnit $unit, public readonly Scalar $source)
    {
    }

    /**
     * Derives the operand; the result is an integer.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ResultTyping())->fact('IY', [(new Arguments())->one($this->source, $derivation, $environment)]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXTRACT')->glue()->symbol('(')->keyword($this->unit->value)->keyword('FROM')->node($this->source)->symbol(')');
    }
}
