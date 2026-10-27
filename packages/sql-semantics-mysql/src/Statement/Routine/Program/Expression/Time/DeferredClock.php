<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored clock call whose fractional precision is refused when reached.
 *
 * The NUM token is retained exactly; its effective precision is modulo 256.
 * This operand is not a Scalar. A skipped instruction never prepares it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility public
 * @example Retaining an invocation-time precision condition
 *     $clock = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('263'));
 *     [$clock instanceof \SqlSemantics\Statement\Scalar, $clock->problem()->precision] // => [false, 7]
 */
final class DeferredClock implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Clock $clock The time function
     * @param Numeral $precision The complete decimal NUM operand
     */
    public function __construct(public readonly Clock $clock, public readonly Numeral $precision, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input($clock->precise() && $precision->isIntegerToken() && (int) $precision->text % 256 > 6, 'A deferred clock retains a NUM precision whose effective value exceeds six.');
    }

    /**
     * Retains the semantic condition without preparing the stored instruction.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Invalid($this->problem()), Nullability::NotNull);
    }

    /**
     * @return list<\SqlSemantics\Statement\Scalar> No runtime operands precede the fixed precision
     */
    public function operands(): array
    {
        return [];
    }

    /**
     * Answers the effective precision and the diagnostic name of the clock.
     */
    public function problem(): TooBigPrecision
    {
        return new TooBigPrecision((int) $this->precision->text % 256, strtolower($this->clock->value));
    }

    /**
     * Writes the retained clock and its precision.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->clock->value)->glue()->symbol('(')->node($this->precision)->symbol(')');
    }
}
