<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;

/**
 * A call of WEIGHT_STRING(): the binary weight string of a string under its collation.
 *
 * Rule: MYSQL-WEIGHT-STRING-001. The argument may be cast to CHAR(n) or
 * BINARY(n) first; MySQL 5.6 and 5.7 also take a LEVEL clause of levels or a
 * range of levels, and not after a BINARY cast. The result is a binary
 * string; it is NULL when the argument is NULL. The weight of a number, of a
 * binary string, or of a value cast to BINARY(n) is a VARBINARY of the length
 * of the value as text, or of n, and at least 8 (verified on a live 8.4
 * server). Collation-specific expansion determines other string capacities;
 * AS CHAR retains at least the original operand byte capacity before expansion.
 * Terminates: the operand is a strict part.
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
 * @example Refusing a zero cast length before execution
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast::Binary, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('00000')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Retaining a fractional cast length for its condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast::Char, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.0')))->length?->text // => '1.0'
 */
final class WeightString implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
        ?ScalarFact $scalarInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $this->levels = Check::listOf($levels, WeightLevel::class, 'A LEVEL clause is a list of levels.');
        Check::input(($cast === null) === ($length === null), 'A cast of WEIGHT_STRING has a length, and only a cast has one.');
        $magnitudes = new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes();
        Check::input($length === null || !$magnitudes->integral($length) || !$magnitudes->atMost($length, '0'), 'An integral WEIGHT_STRING cast length is positive.');
        Check::input($levels === [] || $range === null, 'A LEVEL clause is a list or a range.');
        Check::input($cast !== WeightCast::Binary || ($levels === [] && $range === null), 'WEIGHT_STRING takes no LEVEL clause after a binary cast.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand; the result is a binary string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Arguments())->one($this->subject, $derivation, $environment);
        if ($this->length !== null && !(new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integer($derivation, $this->length)) {
            return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber($this->length)), \SqlSemantics\Statement\Type\Nullability::Dependent);
        }
        return (new \SqlSemantics\Platform\MySql\Rules\Call\WeightResults())->fact($fact, $derivation, $this->cast, $this->castLength());
    }

    /**
     * Answers the cast's unsigned 32-bit length, or null without a cast.
     * A positive token can narrow to zero; its spelling remains available for rendering.
     */
    public function castLength(): ?int
    {
        return $this->length === null || !(new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integral($this->length) ? null : (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->unsigned32($this->length);
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
