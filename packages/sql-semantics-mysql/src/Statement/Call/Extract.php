<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of EXTRACT(unit FROM date): the part of a date or time a unit names.
 *
 * Rule: MYSQL-EXTRACT-CALL-001. The result is an integer; it is NULL when
 * the argument is NULL or not a valid date or time. Terminates: the operand
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_extract.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing EXTRACT()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT EXTRACT(YEAR_MONTH FROM '2024-07-02')");
 *     $query->field(0)->type->descriptor->name() // => 'BIGINT'
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
