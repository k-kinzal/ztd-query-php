<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A typed PostgreSQL bigint value.
 *
 * @visibility public
 * @example Holding the lower bigint endpoint
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral('-9223372036854775808'))->decimal // => '-9223372036854775808'
 */
final class WideIntegerLiteral
{
    use Snapshot;

    /**
     * Checks a canonical decimal in the signed 64-bit range.
     */
    public function __construct(public readonly string $decimal)
    {
        Check::input(preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/', $decimal) === 1, 'Expected a normalized signed decimal bigint.');
        $magnitude = ltrim($decimal, '-');
        $maximum = $decimal[0] === '-' ? '9223372036854775808' : '9223372036854775807';
        Check::input(strlen($magnitude) < 19 || strcmp($magnitude, $maximum) <= 0, 'Integer is outside the signed 64-bit range.');
    }
}
