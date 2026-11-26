<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A decimal integer literal, kept as its exact digits.
 *
 * Digit separators are not part of the value and are not kept.
 *
 * Rule: SQLITE-INTEGER-LITERAL-001. A literal that fits a signed 64-bit
 * integer has storage class INTEGER; a larger one is read as REAL.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing text that is not a digit sequence
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1_000') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing an empty digit sequence
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing a sign
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('-1') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class IntegerLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $digits The decimal digits without sign or separators
     */
    public function __construct(public readonly string $digits)
    {
        Check::input(preg_match('/\A[0-9]+\z/', $digits) === 1, 'An integer literal is a sequence of decimal digits.');
    }

    /**
     * Tells whether the value fits a signed 64-bit integer.
     */
    public function fits(): bool
    {
        $significant = ltrim($this->digits, '0');

        return strlen($significant) < 19 || (strlen($significant) === 19 && strcmp($significant, '9223372036854775807') <= 0);
    }

    /**
     * Derives the storage class from the magnitude.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known($this->fits() ? Storage::Integer : Storage::Real), Nullability::NotNull);
    }

    /**
     * Writes the digits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->digits);
    }
}
