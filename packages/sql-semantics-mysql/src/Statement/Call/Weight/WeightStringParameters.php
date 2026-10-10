<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The internal form WEIGHT_STRING(string, length, weights, flags) the server writes into view definitions.
 *
 * Rule: MYSQL-WEIGHT-STRING-002. The three numbers are the result length,
 * the number of weights and formatting flags. The
 * result is a binary string; it is NULL when the argument is NULL.
 * Terminates: the operand is a strict part.
 * Result capacities and the four-argument form are verified through live SQL on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing the internal form
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT WEIGHT_STRING('ab', 0, 4, 64)");
 *     $query->field(0)->type->descriptor->name() // => 'VARBINARY'
 */
final class WeightStringParameters implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $subject The string
     * @param Numeral $length The result length
     * @param Numeral $weights The number of weights
     * @param Numeral $flags The flags
     */
    public function __construct(public readonly Scalar $subject, public readonly Numeral $length, public readonly Numeral $weights, public readonly Numeral $flags)
    {
    }

    /**
     * Derives the operand; the result is a binary string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Call\WeightResults())->fact((new Arguments())->one($this->subject, $derivation, $environment), $derivation, resultLength: (int) $this->length->text, weights: (int) $this->weights->text);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('WEIGHT_STRING')->glue()->symbol('(')->node($this->subject)->symbol(',')->node($this->length)->symbol(',')
            ->node($this->weights)->symbol(',')->node($this->flags)->symbol(')');
    }
}
