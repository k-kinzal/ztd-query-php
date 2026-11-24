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
 * A floating point literal, kept as its exact decimal parts and never as a PHP float.
 *
 * Rule: SQLITE-REAL-LITERAL-001. A numeric literal with a decimal point or an
 * exponent has storage class REAL.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a literal with neither a decimal point nor an exponent
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1', null, null) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing a point without any digit
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('', '') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing non digit parts
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1a', '5') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing an exponent with a letter
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1', null, 'e5') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class RealLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $whole The digits before the decimal point; empty when the literal starts with the point
     * @param string|null $fraction The digits after the decimal point; null when the literal has no point
     * @param string|null $exponent The exponent digits with their optional sign; null when the literal has no exponent
     * @param bool $uppercaseExponent Whether the exponent marker is capitalized
     */
    public function __construct(
        public readonly string $whole,
        public readonly ?string $fraction,
        public readonly ?string $exponent = null,
        public readonly bool $uppercaseExponent = false,
    ) {
        Check::input(preg_match('/\A[0-9]*\z/', $whole) === 1 && preg_match('/\A[0-9]*\z/', $fraction ?? '') === 1, 'The parts of a floating point literal are decimal digits.');
        Check::input($whole !== '' || ($fraction !== null && $fraction !== ''), 'A floating point literal has a digit before or after its decimal point.');
        Check::input($fraction !== null || $exponent !== null, 'A floating point literal has a decimal point or an exponent.');
        Check::input($exponent === null || preg_match('/\A[+-]?[0-9]+\z/', $exponent) === 1, 'An exponent is an optionally signed sequence of decimal digits.');
        Check::input(!$uppercaseExponent || $exponent !== null, 'An uppercase exponent marker needs exponent digits.');
    }

    /**
     * Derives REAL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Storage::Real), Nullability::NotNull);
    }

    /**
     * Writes the parts in order.
     */
    public function render(Output $out): void
    {
        $exponent = $this->exponent === null ? '' : ($this->uppercaseExponent ? 'E' : 'e') . $this->exponent;
        $out->spelled($this->whole . ($this->fraction === null ? '' : '.' . $this->fraction) . $exponent);
    }
}
