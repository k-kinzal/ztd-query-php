<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Strings;
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
 * A standard date and time literal: `DATE 'text'`, `TIME 'text'` or `TIMESTAMP 'text'`.
 *
 * Rule: MYSQL-TEMPORAL-LITERAL-001. Facts: DATE yields DATE, TIME yields
 * TIME, and TIMESTAMP yields DATETIME; the fractional seconds precision of
 * TIME and DATETIME is the number of digits written after the last decimal
 * point, before an optional time zone offset, at most 6; never NULL. Text
 * the server cannot read as a value of the kind is an error of the statement
 * when it runs and is not decided here. Precision: exact for text the server
 * accepts. Diagnostics: none. A literal spelled under another escape rule
 * than the profile is an invalid construction.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a temporal literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = TIMESTAMP '2024-01-02 03:04:05.25'");
 *     [$query->statement->where->right->form, $query->statement->where->right->text, $query->statement->where->right->type()->precision] // => [\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, '2024-01-02 03:04:05.25', '2']
 */
final class TemporalLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param TemporalForm $form The keyword of the literal
     * @param string $text The decoded text
     * @param EscapeRule $escapes The rule the text is spelled under; the rule of the language profile
     */
    public function __construct(public readonly TemporalForm $form, public readonly string $text, public readonly EscapeRule $escapes = EscapeRule::Backslash)
    {
    }

    /**
     * Answers the type of the literal.
     */
    public function type(): Temporal
    {
        if ($this->form === TemporalForm::Date) {
            return new Temporal(TemporalKind::Date);
        }
        $digits = preg_match('/\.([0-9]*)(?:[+-][0-9]{1,2}:[0-9]{2})?\s*\z/', $this->text, $match) === 1 ? min(6, strlen($match[1])) : 0;

        return new Temporal($this->form === TemporalForm::Time ? TemporalKind::Time : TemporalKind::DateTime, $digits === 0 ? null : (string) $digits);
    }

    /**
     * Derives the temporal type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input($this->escapes === EscapeRule::under($derivation->context->profile->lexical), 'A temporal literal must be spelled under the escape rule of the language profile.');

        return new ScalarFact(new Known($this->type()), Nullability::NotNull);
    }

    /**
     * Writes the keyword and the quoted text.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->form->value)->spelled((new Strings())->encode($this->text, $this->escapes === EscapeRule::Backslash));
    }
}
