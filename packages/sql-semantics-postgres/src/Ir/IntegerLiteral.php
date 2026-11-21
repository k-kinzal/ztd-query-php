<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A signed 32-bit PostgreSQL integer.
 *
 * @visibility public
 * @example Holding PostgreSQL's smallest integer
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('-2147483648'))->decimal // => '-2147483648'
 */
final class IntegerLiteral
{
    use Snapshot;

    /**
     * Checks the canonical decimal representation and the signed 32-bit range.
     */
    public function __construct(public readonly string $decimal)
    {
        Check::input(preg_match('/\A(?:0|-?[1-9][0-9]{0,9})\z/', $decimal) === 1, 'Expected a normalized signed decimal integer.');
        $magnitude = ltrim($decimal, '-');
        $maximum = $decimal[0] === '-' ? '2147483648' : '2147483647';
        Check::input(strlen($magnitude) < 10 || strcmp($magnitude, $maximum) <= 0, 'Integer is outside the signed 32-bit range.');
    }
}
