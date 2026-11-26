<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed 64-bit integer constant in a MySQL range predicate or integral conversion.
 *
 * @visibility public
 * @example Keeping a signed endpoint beyond the table column domain
 *     (new \SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral('-9223372036854775808'))->decimal // => '-9223372036854775808'
 */
final class RangeIntegerLiteral
{
    use Snapshot;

    /**
     * Fixes the numeric value and the effective sign and width of the token.
     */
    public function __construct(
        public readonly string $decimal,
        public readonly int $leadingZeroes = 0,
        public readonly bool $negativeZero = false,
    ) {
        Check::input(preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/', $decimal) === 1, 'Expected a normalized signed range integer.');
        $magnitude = ltrim($decimal, '-');
        $maximum = $decimal[0] === '-' ? '9223372036854775808' : '9223372036854775807';
        Check::input(strlen($magnitude) < 19 || strcmp($magnitude, $maximum) <= 0, 'Range integer is outside the signed 64-bit range.');
        Check::input($leadingZeroes >= 0 && $leadingZeroes <= IntegerLiteral::MAX_LEADING_ZEROES, 'Range integer padding exceeds the effective field width.');
        Check::input(!$negativeZero || $decimal === '0', 'A separate negative sign applies only to zero.');
    }

    /**
     * Writes the validated numeric token.
     */
    public function toString(): string
    {
        $sign = $this->negativeZero || $this->decimal[0] === '-' ? '-' : '';

        return $sign . str_repeat('0', $this->leadingZeroes) . ltrim($this->decimal, '-');
    }
}
