<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A number with an optional minus sign, written where the grammar reads `NumericOnly` instead of an expression.
 *
 * Sequence options, storage parameters and configuration values take such
 * numbers. A written plus sign changes nothing and is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html.
 *
 * @visibility public
 * @example Reading a negative option value
 *     $number = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('5'));
 *     [$number->negative, $number->magnitude->digits] // => [true, '5']
 */
final class SignedNumber implements OptionArgument
{
    use Snapshot;

    /**
     * @param bool $negative Whether a minus sign is written
     * @param IntegerConstant|NumericConstant $magnitude The number after the sign
     */
    public function __construct(public readonly bool $negative, public readonly IntegerConstant|NumericConstant $magnitude)
    {
    }

    /**
     * Derives nothing: a number holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the minus sign and the number.
     */
    public function render(Output $out): void
    {
        if ($this->negative) {
            $out->symbol('-');
        }
        $out->node($this->magnitude);
    }
}
