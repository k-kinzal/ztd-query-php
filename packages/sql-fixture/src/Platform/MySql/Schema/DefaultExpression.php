<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Analysis\NumericLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Scalar;

/**
 * Evaluates the literal of a DEFAULT clause into the PHP value it denotes.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Returns the value of the literal; a hexadecimal or bit literal is a number for a numeric column and bytes otherwise.
     */
    public function evaluate(Scalar $literal, bool $numeric): int|float|bool|string|null
    {
        return match (true) {
            $literal instanceof StringLiteral => $literal->value(),
            $literal instanceof NumberLiteral => (new NumericLiteral())->decode($literal->text),
            $literal instanceof SignedLiteral => (new NumericLiteral())->decode(($literal->negative ? '-' : '') . $literal->number->text),
            $literal instanceof BooleanLiteral => $literal->value,
            $literal instanceof TemporalLiteral => $literal->text,
            $literal instanceof RadixLiteral && $numeric => (new NumericLiteral())->decode(($literal->radix === Radix::Hexadecimal ? '0x' : '0b') . $literal->digits),
            $literal instanceof RadixLiteral => (new TypeParameters())->member(new Text($literal->digits, radix: $literal->radix)),
            $literal instanceof NullLiteral => null,
            default => null,
        };
    }
}
