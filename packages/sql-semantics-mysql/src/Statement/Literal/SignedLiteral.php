<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A number literal written with a sign at a position that takes a signed literal, such as a column default.
 *
 * In an expression a sign is the unary operator; at these positions the
 * grammar reads the sign as part of the literal.
 *
 * Rule: MYSQL-SIGNED-LITERAL-001. Facts: the type of the number, except that
 * the negation of an integer beyond the signed 64-bit range, 9223372036854775808
 * and above, is DECIMAL: the server negates an unsigned integer literal through
 * a decimal value (Item_uint::neg in sql/item.cc); never NULL. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html,
 * https://github.com/mysql/mysql-server/blob/8.4/sql/item.cc. Status: Implemented.
 *
 * @visibility public
 * @example Holding a negative literal
 *     $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral(true, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('5'));
 *     [$literal->negative, $literal->number->text] // => [true, '5']
 */
final class SignedLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param bool $negative Whether the sign is minus; otherwise plus
     * @param NumberLiteral $number The unsigned number
     */
    public function __construct(public readonly bool $negative, public readonly NumberLiteral $number)
    {
    }

    /**
     * Derives the number and applies the range rule of negation.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->number, $environment);
        if ($this->negative && $this->number->beyondSigned()) {
            return new ScalarFact(new Known(new Decimal()), $fact->nullability);
        }

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the sign and the number.
     */
    public function render(Output $out): void
    {
        $out->symbol($this->negative ? '-' : '+')->node($this->number);
    }
}
