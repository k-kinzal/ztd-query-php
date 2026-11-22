<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Literal;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A signed SQLite INTEGER spelled in hexadecimal.
 *
 * @visibility public
 * @example Holding a two's-complement hexadecimal integer
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral('FFFFFFFFFFFFFFFF'))->digits // => 'FFFFFFFFFFFFFFFF'
 */
final class HexIntegerLiteral
{
    use Snapshot;

    /**
     * The negation is applied after interpreting digits as a signed 64-bit integer.
     */
    public function __construct(public readonly string $digits, public readonly bool $negated = false)
    {
        Check::input(preg_match('/\A(?:0|[1-9A-F][0-9A-F]{0,15})\z/', $digits) === 1, 'Hexadecimal INTEGER requires up to sixteen significant upper-case digits.');
        Check::input(!$negated || $digits !== '8000000000000000', 'The minimum hexadecimal INTEGER cannot be negated.');
    }

    /**
     * Emits the signed hexadecimal INTEGER spelling.
     */
    public function toString(): string
    {
        return ($this->negated ? '-' : '') . '0x' . $this->digits;
    }
}
