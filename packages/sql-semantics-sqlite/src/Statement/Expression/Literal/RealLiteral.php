<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A floating point literal, kept as its exact decimal parts and never as a PHP float.
 *
 * Rule: SQLITE-REAL-LITERAL-001. A numeric literal with a decimal point or an
 * exponent has storage class REAL.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a floating point literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1.50E-3 AS r');
 *     $literal = $query->statement->columns[0]->expression;
 *     [$literal->whole, $literal->fraction, $literal->exponent, $query->toString()] // => ['1', '50', '-3', 'SELECT 1.50e-3 AS r']
 * @example Refusing a literal with neither a decimal point nor an exponent
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1', null, null) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a point without any digit
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('', '') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing non digit parts
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1a', '5') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an exponent with a letter
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral('1', null, 'e5') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RealLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $whole The digits before the decimal point; empty when the literal starts with the point
     * @param string|null $fraction The digits after the decimal point; null when the literal has no point
     * @param string|null $exponent The exponent digits with their optional sign; null when the literal has no exponent
     */
    public function __construct(public readonly string $whole, public readonly ?string $fraction, public readonly ?string $exponent = null)
    {
        Check::input(preg_match('/\A[0-9]*\z/', $whole) === 1 && preg_match('/\A[0-9]*\z/', $fraction ?? '') === 1, 'The parts of a floating point literal are decimal digits.');
        Check::input($whole !== '' || ($fraction !== null && $fraction !== ''), 'A floating point literal has a digit before or after its decimal point.');
        Check::input($fraction !== null || $exponent !== null, 'A floating point literal has a decimal point or an exponent.');
        Check::input($exponent === null || preg_match('/\A[+-]?[0-9]+\z/', $exponent) === 1, 'An exponent is an optionally signed sequence of decimal digits.');
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
        $out->spelled($this->whole . ($this->fraction === null ? '' : '.' . $this->fraction) . ($this->exponent === null ? '' : 'e' . $this->exponent));
    }
}
