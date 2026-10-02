<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A hexadecimal integer literal, kept as its exact hexadecimal digits in upper case.
 *
 * Rule: SQLITE-HEX-LITERAL-001. A hexadecimal literal is the two's complement
 * 64-bit integer its digits spell and has storage class INTEGER; more than 16
 * significant digits are rejected by SQLite as too big.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the digits of a hexadecimal literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 0x1f');
 *     [$query->statement->columns[0]->expression->digits, $query->toString()] // => ['1F', 'SELECT 0x1F']
 * @example Refusing lower-case digits, which are another spelling of the same value
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral('1f') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class HexLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $digits The hexadecimal digits in upper case, without prefix or separators
     */
    public function __construct(public readonly string $digits)
    {
        Check::input(preg_match('/\A[0-9A-F]+\z/', $digits) === 1, 'A hexadecimal literal is a sequence of upper-case hexadecimal digits.');
    }

    /**
     * Derives INTEGER, or the problem of a literal wider than 64 bits.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if (strlen(ltrim($this->digits, '0')) > 16) {
            $problem = new Misuse(MisuseRule::HexLiteralTooBig);
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::NotNull);
        }

        return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
    }

    /**
     * Writes the prefix and the digits.
     */
    public function render(Output $out): void
    {
        $out->spelled('0x' . $this->digits);
    }
}
