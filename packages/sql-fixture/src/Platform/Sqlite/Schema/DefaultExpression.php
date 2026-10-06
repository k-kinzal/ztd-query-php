<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Analysis\NumericLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

/**
 * Evaluates a DEFAULT clause into the PHP value it stores.
 *
 * A literal and a word have a value, and a bare TRUE or FALSE is a boolean;
 * a quoted word keeps its text. An expression in parentheses and
 * CURRENT_TIMESTAMP and its siblings are computed when a row is inserted and
 * have none.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Returns the value of a default literal or word.
     */
    public function evaluate(DefaultLiteral|DefaultWord $default): int|float|bool|string|null
    {
        if ($default instanceof DefaultWord) {
            return $default->truth() ?? $default->word->name->value;
        }
        $literal = $default->literal;
        $sign = $default->sign === NumberSign::Minus ? '-' : '';

        return match (true) {
            $literal instanceof TextLiteral => $literal->value,
            $literal instanceof BlobLiteral => (string) hex2bin($literal->hex),
            $literal instanceof IntegerLiteral => (new NumericLiteral())->decode($sign . $literal->digits),
            $literal instanceof HexLiteral => $default->sign === NumberSign::Minus ? -$this->hexadecimal($literal->digits) : $this->hexadecimal($literal->digits),
            $literal instanceof RealLiteral => (new NumericLiteral())->decode($sign . $literal->whole . ($literal->fraction === null ? '' : '.' . $literal->fraction) . ($literal->exponent === null ? '' : 'e' . $literal->exponent)),
            default => null,
        };
    }

    /**
     * Returns the integer hexadecimal digits denote, read as SQLite reads them: as a 64-bit two's-complement integer.
     */
    public function hexadecimal(string $digits): int
    {
        $value = 0;
        foreach (str_split($digits) as $digit) {
            $value = ($value << 4) | (int) hexdec($digit);
        }

        return $value;
    }
}
