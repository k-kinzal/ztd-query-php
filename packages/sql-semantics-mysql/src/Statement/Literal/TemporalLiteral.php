<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A standard date and time literal: `DATE 'text'`, `TIME 'text'` or `TIMESTAMP 'text'`.
 *
 * Rule: MYSQL-TEMPORAL-LITERAL-001. Facts: DATE yields DATE, TIME yields
 * TIME, and TIMESTAMP yields DATETIME; the fractional seconds precision of
 * TIME and DATETIME is the number of digits written after the last decimal
 * point, before an optional time zone offset, at most 6; never NULL. Text
 * outside the temporal representation domain is refused during construction.
 * Calendar validity and zero dates can depend on session modes;
 * precision: exact for text the server
 * accepts. Diagnostics: none. The text holds decoded bytes independent of
 * the lexical mode used to write them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html. Status: Implemented.
 *
 * @visibility public
 * @example Refusing text outside the DATE domain
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, 'not a date') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing text outside the TIME domain
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time, '839:00:00') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an arbitrary TIME operand
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time, 'bad') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a TIME offset
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time, '10:11:12.123-01:00') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an oversized compact TIME token
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time, '000000000001') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a TIMESTAMP without a clock
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, '2024-01-02') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an arbitrary TIMESTAMP operand
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, 'bad') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a TIMESTAMP that overflows during fractional rounding
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, '9999-12-31 23:59:59.9999999') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Retaining a date permitted by ALLOW_INVALID_DATES
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, '2024-02-31'))->text // => '2024-02-31'
 * @example Reading a temporal literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = TIMESTAMP '2024-01-02 03:04:05.25'");
 *     [$query->query->where->right->form, $query->query->where->right->text, $query->query->where->right->type()->precision] // => [\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, '2024-01-02 03:04:05.25', '2']
 */
final class TemporalLiteral implements LiteralExpression
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param TemporalForm $form The keyword of the literal
     * @param string $text The decoded text
     */
    public function __construct(public readonly TemporalForm $form, public readonly string $text, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        \SqlSemantics\Diagnostic\Check::input($form === TemporalForm::Timestamp ? \SqlSemantics\Platform\MySql\Rules\Literal\Timestamp::valid($text) : \SqlSemantics\Platform\MySql\Rules\Literal\Temporal::literal($form->value, $text, 6, false, false, true) !== null, 'A temporal literal requires a bounded representation.');
    }

    /**
     * Answers the type of the literal.
     */
    public function type(): Temporal
    {
        if ($this->form === TemporalForm::Date) {
            return new Temporal(TemporalKind::Date);
        }
        $digits = $this->form === TemporalForm::Timestamp ? min(6, strlen(\SqlSemantics\Platform\MySql\Rules\Literal\Timestamp::parts($this->text)[6] ?? '')) : (preg_match('/\.([0-9]*)\s*\z/', $this->text, $match) === 1 ? min(6, strlen($match[1])) : 0);

        return new Temporal($this->form === TemporalForm::Time ? TemporalKind::Time : TemporalKind::DateTime, $digits === 0 ? null : (string) $digits);
    }

    /**
     * Derives the temporal type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Literals::of($derivation->context)->temporal($this)), Nullability::NotNull);
    }

    /**
     * Writes the keyword and the quoted text.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->form->value)->spelled($out->quote($this->text));
    }
}
