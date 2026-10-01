<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * The comparison policy of a namespace after identifier decoding and folding.
 * @visibility public
 * @example Comparing names in an ASCII-insensitive namespace
 *     \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive->equal('Foo', 'foo') // => true
 */
enum Comparison
{
    case Sensitive;
    case AsciiInsensitive;

    /**
     * Compares decoded names without changing their original spelling.
     */
    public function equal(string $left, string $right): bool
    {
        return $this === self::Sensitive ? $left === $right : strcasecmp($left, $right) === 0;
    }
}
