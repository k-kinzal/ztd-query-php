<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A signed 64-bit SQLite integer.
 *
 * @visibility public
 * @example Holding SQLite's smallest integer
 *     (new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('-9223372036854775808'))->decimal // => '-9223372036854775808'
 */
final class IntegerLiteral
{
    use Snapshot;

    /**
     * Checks the canonical decimal representation and the signed 64-bit range.
     */
    public function __construct(public readonly string $decimal)
    {
        Check::input(preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/', $decimal) === 1, 'Expected a normalized signed decimal integer.');
        $magnitude = ltrim($decimal, '-');
        $maximum = $decimal[0] === '-' ? '9223372036854775808' : '9223372036854775807';
        Check::input(strlen($magnitude) < 19 || strcmp($magnitude, $maximum) <= 0, 'Integer is outside the signed 64-bit range.');
    }
}
