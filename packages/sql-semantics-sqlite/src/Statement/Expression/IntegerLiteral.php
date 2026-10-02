<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

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
 * A decimal integer literal, kept as its exact digits.
 *
 * Rule: SQLITE-INTEGER-LITERAL-001. A literal that fits a signed 64-bit
 * integer has storage class INTEGER; a larger one is read as REAL.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Keeping the exact digits of a literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 9223372036854775808');
 *     [$query->statement->columns[0]->expression->digits, $query->field(0)->type->descriptor] // => ['9223372036854775808', \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Real]
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
     * Derives the storage class from the magnitude.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $significant = ltrim($this->digits, '0');
        $fits = strlen($significant) < 19 || (strlen($significant) === 19 && strcmp($significant, '9223372036854775807') <= 0);

        return new ScalarFact(new Known($fits ? Storage::Integer : Storage::Real), Nullability::NotNull);
    }

    /**
     * Writes the digits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->digits);
    }
}
