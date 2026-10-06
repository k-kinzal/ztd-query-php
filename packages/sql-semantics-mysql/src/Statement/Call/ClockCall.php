<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of a function that reads the current date or time, with its optional fractional seconds precision.
 *
 * Rule: MYSQL-CLOCK-CALL-001. The result is a DATETIME, TIME or DATE with
 * the written precision and is never NULL. A precision above 6 is the error
 * ER_TOO_BIG_PRECISION, which the server reports when it resolves the call;
 * the precision is kept as written. The function is written with
 * parentheses, which are optional and change nothing (`NOW` alone would be
 * a column name).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_now,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing the current timestamp with a precision
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CURRENT_TIMESTAMP(3)');
 *     [$query->field(0)->type->descriptor->precision, $query->field(0)->nullability] // => ['3', \SqlSemantics\Statement\Type\Nullability::NotNull]
 * @example Refusing a precision for a date
 *     new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::CurrentDate, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ClockCall implements Scalar
{
    use Snapshot;

    /**
     * @param Clock $clock The function
     * @param Numeral|null $precision The fractional seconds precision, when written
     * @param OptionalWords $parentheses Whether the parentheses are written when they hold no precision
     */
    public function __construct(public readonly Clock $clock, public readonly ?Numeral $precision = null, public readonly OptionalWords $parentheses = OptionalWords::Written)
    {
        Check::input($precision === null || ($clock->precise() && !$precision->hexadecimal && ctype_digit($precision->text)), 'Only a time function takes a precision, and a precision is a decimal integer.');
        Check::input($parentheses === OptionalWords::Written || ($precision === null && $clock->bare() !== null), 'A precision is written in parentheses, and SYSDATE is only written with them.');
    }

    /**
     * Answers the current date or time, never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $type = $this->clock->result()->descriptor();
        if ($this->precision !== null && $type instanceof Temporal) {
            $type = new Temporal($type->kind === TemporalKind::Time ? TemporalKind::Time : TemporalKind::DateTime, $this->precision->text);
        }

        return new ScalarFact(new Known($type), Nullability::NotNull);
    }

    /**
     * Writes the keyword against its parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->parentheses === OptionalWords::Omitted) {
            $out->keyword((string) $this->clock->bare());

            return;
        }
        $out->keyword($this->clock->value)->glue()->symbol('(')->node($this->precision)->symbol(')');
    }
}
