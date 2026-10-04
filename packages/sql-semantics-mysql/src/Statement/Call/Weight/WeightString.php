<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of WEIGHT_STRING(): the binary weight string of a string under its collation.
 *
 * Rule: MYSQL-WEIGHT-STRING-001. The argument may be cast to CHAR(n) or
 * BINARY(n) first; MySQL 5.6 and 5.7 also take a LEVEL clause of levels or a
 * range of levels, and not after a BINARY cast. The result is a binary
 * string; it is NULL when the argument is NULL. Terminates: the operand is a
 * strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string,
 * https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_weight-string.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing WEIGHT_STRING()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT WEIGHT_STRING('ab' AS CHAR(4))");
 *     $query->field(0)->type->descriptor->name() // => 'VARBINARY'
 * @example Refusing levels after a binary cast
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast::Binary, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4'), [new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class WeightString implements Scalar
{
    use Snapshot;

    /**
     * @var list<WeightLevel> The levels of the LEVEL clause
     */
    public readonly array $levels;

    /**
     * @param Scalar $subject The string
     * @param WeightCast|null $cast The type the string is cast to
     * @param Numeral|null $length The length of the cast; present exactly with a cast
     * @param list<WeightLevel> $levels The levels of the LEVEL clause
     * @param WeightLevelRange|null $range The range of the LEVEL clause
     */
    public function __construct(
        public readonly Scalar $subject,
        public readonly ?WeightCast $cast = null,
        public readonly ?Numeral $length = null,
        array $levels = [],
        public readonly ?WeightLevelRange $range = null,
    ) {
        $this->levels = Check::listOf($levels, WeightLevel::class, 'A LEVEL clause is a list of levels.');
        Check::input(($cast === null) === ($length === null), 'A cast of WEIGHT_STRING has a length, and only a cast has one.');
        Check::input($levels === [] || $range === null, 'A LEVEL clause is a list or a range.');
        Check::input($cast !== WeightCast::Binary || ($levels === [] && $range === null), 'WEIGHT_STRING takes no LEVEL clause after a binary cast.');
    }

    /**
     * Derives the operand; the result is a binary string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ResultTyping())->fact('BY', [(new Arguments())->one($this->subject, $derivation, $environment)]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('WEIGHT_STRING')->glue()->symbol('(')->node($this->subject);
        if ($this->cast !== null) {
            $out->keyword('AS', $this->cast->value)->glue()->symbol('(')->node($this->length)->symbol(')');
        }
        if ($this->levels !== [] || $this->range !== null) {
            $out->keyword('LEVEL')->list($this->levels)->node($this->range);
        }
        $out->symbol(')');
    }
}
