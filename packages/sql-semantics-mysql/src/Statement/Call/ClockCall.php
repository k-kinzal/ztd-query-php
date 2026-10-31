<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Typing\Moments;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of a function that reads the current date or time, with its optional fractional seconds precision.
 *
 * Rule: MYSQL-CLOCK-CALL-001. The result is a DATETIME, TIME or DATE with
 * the written precision and is never NULL. The server keeps the written
 * precision in one byte, so it is read modulo 256: a precision of 256 is 0.
 * Ordinary Scalar calls require the effective precision to be at most 6.
 * Stored instructions retain a larger effective precision in DeferredClock,
 * outside Scalar. Column timestamp clauses have their own attribute role.
 * The precision is kept as written. The function is written with
 * parentheses, which are optional and change nothing (`NOW` alone would be
 * a column name).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_now,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing the current timestamp with a precision
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CURRENT_TIMESTAMP(3)');
 *     [$query->field(0)->type->descriptor->decimals, $query->field(0)->nullability] // => [3, \SqlSemantics\Statement\Type\Nullability::NotNull]
 * @example Refusing a precision for a date
 *     new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::CurrentDate, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ClockCall implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Clock $clock The function
     * @param Numeral|null $precision The fractional seconds precision, when written
     * @param OptionalWords $parentheses Whether the parentheses are written when they hold no precision
     */
    public function __construct(public readonly Clock $clock, public readonly ?Numeral $precision = null, public readonly OptionalWords $parentheses = OptionalWords::Written, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?Keyword\ClockAlias $alias = null)
    {
        Check::input($alias === null || $alias->clock() === $clock, 'A clock synonym identifies its clock operation.');
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input($precision === null || ($clock->precise() && $precision->isIntegerToken()), 'Only a time function takes a precision, and a precision is a NUM integer token.');
        Check::input($precision === null || (int) $precision->text % 256 <= 6, 'A scalar clock precision resolves to at most six fractional seconds digits.');
        Check::input($parentheses === OptionalWords::Written || ($precision === null && $clock->bare() !== null), 'A precision is written in parentheses, and SYSDATE is only written with them.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers the current date or time, never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $decimals = $this->decimals();

        return new ScalarFact(new Known((new Moments(Settings::of($derivation->context)))->clock($this->clock, $decimals)), Nullability::NotNull);
    }

    /**
     * Answers the fractional seconds precision the server reads: the written one modulo 256, or 0 when none is written.
     */
    public function decimals(): int
    {
        return $this->precision === null ? 0 : (int) $this->precision->text % 256;
    }

    /**
     * Writes the keyword against its parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->parentheses === OptionalWords::Omitted) {
            $out->keyword($this->alias->value ?? (string) $this->clock->bare());

            return;
        }
        $out->keyword($this->alias->value ?? $this->clock->value)->glue()->symbol('(')->node($this->precision)->symbol(')');
    }
}
