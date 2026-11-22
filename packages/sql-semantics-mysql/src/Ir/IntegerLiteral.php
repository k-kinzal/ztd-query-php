<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed 32-bit MySQL integer.
 *
 * @visibility public
 * @example Holding MySQL's smallest integer
 *     (new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('-2147483648'))->decimal // => '-2147483648'
 */
final class IntegerLiteral
{
    use Snapshot;

    /**
     * The saturation point above the materialized integer field type thresholds.
     */
    public const MAX_LEADING_ZEROES = 32;

    /**
     * Checks the canonical decimal representation and the signed 32-bit range.
     *
     * The leading zero count contributes to the server's materialized field type.
     */
    public function __construct(
        public readonly string $decimal,
        public readonly int $leadingZeroes = 0,
        public readonly bool $negativeZero = false,
    ) {
        Check::input(preg_match('/\A(?:0|-?[1-9][0-9]{0,9})\z/', $decimal) === 1, 'Expected a normalized signed decimal integer.');
        $magnitude = ltrim($decimal, '-');
        $maximum = $decimal[0] === '-' ? '2147483648' : '2147483647';
        Check::input(strlen($magnitude) < 10 || strcmp($magnitude, $maximum) <= 0, 'Integer is outside the signed 32-bit range.');
        Check::input($leadingZeroes >= 0 && $leadingZeroes <= self::MAX_LEADING_ZEROES, 'Integer padding exceeds the effective field width.');
        Check::input(!$negativeZero || $decimal === '0', 'A separate negative sign applies only to zero.');
    }

    /**
     * Emits a numeric value with the same effective width and sign.
     */
    public function toString(): string
    {
        $sign = $this->negativeZero || $this->decimal[0] === '-' ? '-' : '';

        return $sign . str_repeat('0', $this->leadingZeroes) . ltrim($this->decimal, '-');
    }
}
