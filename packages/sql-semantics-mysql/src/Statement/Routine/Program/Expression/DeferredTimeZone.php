<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A stored time-zone conversion with an incompatible operand or zone role.
 *
 * The original typed operand is retained for preparation when its instruction
 * is reached. This value is not a Scalar and cannot be embedded in an ordinary
 * query as if its intrinsic operand constraint were satisfied.
 *
 * @visibility public
 * @example Refusing to defer a compatible literal
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Keeping an invocation-time constraint outside Scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
 *     $value instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredTimeZone implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The expression requiring invocation-time preparation
     * @param Text $zone The quoted zone name
     * @param bool $interval Whether INTERVAL precedes the zone
     * @param string|null $precision The fractional seconds precision exactly as written
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, public readonly Text $zone, public readonly bool $interval = false, public readonly ?string $precision = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$operand instanceof Scalar || (new TimeZoneOperands())->input($operand) === false || !(new TimeZoneOperands())->zone($zone), 'A deferred time-zone conversion retains an incompatible operand or zone.');
        Check::input($zone->radix === null, 'A time zone is a quoted string.');
        Check::input($precision === null || preg_match('/\A[0-9]+\z/', $precision) === 1, 'A precision is an unsigned integer.');
        Check::input((int) ($precision ?? '0') <= 6, 'A DATETIME fractional precision is at most six.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the operand without raising the instruction's conversion condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        return new ScalarFact(new Invalid($this->problem()), $fact->nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> The retained preparation dependency
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Answers the intrinsic constraint; dependent operand types still require binding.
     */
    public function problem(): Diagnostic
    {
        return $this->operand instanceof Scalar ? new TimeZoneConversion($this, (new TimeZoneOperands())->input($this->operand) === false) : (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->operand]);
    }

    /**
     * Writes the retained conversion without inventing a substitute scalar.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AT', 'TIME', 'ZONE');
        if ($this->interval) {
            $out->keyword('INTERVAL');
        }
        $out->node($this->zone)->keyword('AS', 'DATETIME');
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->spelled($this->precision)->symbol(')');
        }
        $out->symbol(')');
    }
}
