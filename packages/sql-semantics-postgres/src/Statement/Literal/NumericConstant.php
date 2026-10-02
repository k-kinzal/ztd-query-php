<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The exact value of a numeric constant written with a decimal point or an exponent.
 *
 * The value is the integer digits, the fraction digits and the power of ten;
 * no floating-point number is involved. The fraction digits are kept as
 * written because their count is the display scale of the numeric value, so
 * `1.0` and `1.00` are different constants.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC.
 *
 * @visibility public
 * @example Reading the parts of a numeric constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1_0.50e-3');
 *     $value = $query->statement->targets[0]->expression->value;
 *     [$value->integer, $value->fraction, $value->exponent] // => ['10', '50', '-3']
 * @example Rejecting an exponent of zero, which is written as no exponent
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('1', '5', '0') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class NumericConstant implements Node
{
    use Snapshot;

    /**
     * @param string $integer The decimal digits before the point, without leading zeros
     * @param string $fraction The decimal digits after the point, as many as written
     * @param string|null $exponent The power of ten as a canonical integer other than zero, or null for none
     */
    public function __construct(public readonly string $integer, public readonly string $fraction = '', public readonly ?string $exponent = null)
    {
        Check::input(preg_match('/\A(?:0|[1-9][0-9]*)\z/', $integer) === 1, 'The integer digits of a numeric constant are canonical decimal digits.');
        Check::input(preg_match('/\A[0-9]*\z/', $fraction) === 1, 'The fraction of a numeric constant is decimal digits.');
        Check::input($exponent === null || preg_match('/\A-?[1-9][0-9]*\z/', $exponent) === 1, 'The exponent of a numeric constant is a canonical integer other than zero.');
    }

    /**
     * Writes the digits with a decimal point, so the constant is read as numeric again.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->integer . '.' . $this->fraction . ($this->exponent === null ? '' : 'e' . $this->exponent));
    }
}
