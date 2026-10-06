<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An unsigned number literal, kept as its exact text.
 *
 * Rule: MYSQL-NUMBER-LITERAL-001. Facts: an integer that fits a signed
 * 64-bit integer is BIGINT, one that fits only unsigned is BIGINT UNSIGNED;
 * an exact number with a decimal point or beyond the 64-bit range is
 * DECIMAL; a number with an exponent is DOUBLE; never NULL. Precision: the
 * type family is exact; precision and scale of a DECIMAL literal are not
 * derived. Diagnostics: none. Terminates: constant work.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/precision-math-numbers.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Keeping the exact text of a number
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = 1.50');
 *     [$query->statement->where->right->text, $query->statement->where->right->form] // => ['1.50', \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Decimal]
 */
final class NumberLiteral implements Scalar
{
    use Snapshot;

    /**
     * @var NumberForm The kind of number the text is
     */
    public readonly NumberForm $form;

    /**
     * @param string $text The number exactly as written, without sign
     */
    public function __construct(public readonly string $text)
    {
        $form = NumberForm::of($text);
        Check::input($form !== null, 'A number literal is an unsigned integer, decimal or floating-point number.');
        $this->form = $form;
    }

    /**
     * Tells whether the number is an integer beyond the signed 64-bit range.
     */
    public function beyondSigned(): bool
    {
        $digits = ltrim($this->text, '0');

        return $this->form === NumberForm::Integer && (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, '9223372036854775807') > 0));
    }

    /**
     * Answers the type of the literal.
     */
    public function type(): TypeName
    {
        return match ($this->form) {
            NumberForm::Integer => new Integral(IntegralKind::BigInt, null, $this->beyondSigned() ? [NumericModifier::Unsigned] : []),
            NumberForm::Decimal => new Decimal(),
            NumberForm::Float => new Floating(FloatingKind::Double),
        };
    }

    /**
     * Derives the numeric type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known($this->type()), Nullability::NotNull);
    }

    /**
     * Writes the exact text.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->text);
    }
}
