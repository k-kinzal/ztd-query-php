<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A call of WEIGHT_STRING(): the binary weight string of a string under its collation.
 *
 * Rule: MYSQL-WEIGHT-STRING-001. The argument may be cast to CHAR(n) or
 * BINARY(n) first; MySQL 5.6 and 5.7 also take a LEVEL clause of levels or a
 * range of levels, and not after a BINARY cast. The result is a binary
 * string; it is NULL when the argument is NULL. The weight of a number, of a
 * binary string, or of a value cast to BINARY(n) is a VARBINARY of the length
 * of the value as text, or of n, and at least 8 (verified on a live 8.4
 * server); the length of the weight of another string is not resolved.
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
        $fact = (new Arguments())->one($this->subject, $derivation, $environment);
        $base = (new ResultTyping())->fact('BY', [$fact]);
        $operand = (new Precision())->domain($fact->type);
        if ($operand === null || ($this->cast !== WeightCast::Binary && $operand->kind === Kind::String && $operand->collation->charset !== Charset::binary())) {
            return $base;
        }
        $length = match (true) {
            $this->cast === WeightCast::Binary && $this->length !== null => (int) $this->length->text,
            $operand->kind === Kind::String => $operand->length * $operand->collation->charset->maxLength,
            default => (new Texts(Settings::of($derivation->context)))->length($operand),
        };

        $minimum = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) ? 0 : 8;

        return new ScalarFact(new Known(Domain::string(max($minimum, $length), Collation::binary())), $base->nullability);
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
