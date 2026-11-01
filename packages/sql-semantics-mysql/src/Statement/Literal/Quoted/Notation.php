<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal\Quoted;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A string's delimiter and escape choices, independent of its decoded value.
 *
 * Offsets name decoded bytes. Unmarked control bytes remain literal; unmarked
 * delimiter bytes are doubled. The notation holds no token or source text.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html.
 * @example
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation())->spell("value") // => "'value'"
 *
 * @visibility public
 */
final class Notation
{
    use Snapshot;

    /**
     * @var array<int, Escape> Escapes indexed by their first decoded byte
     */
    public readonly array $escapes;

    /**
     * @param array<int, Escape> $escapes Escapes in increasing decoded-byte order
     */
    public function __construct(public readonly bool $doubleQuoted = false, array $escapes = [], public readonly bool $backslashEscapes = true)
    {
        Check::input($backslashEscapes || $escapes === [], 'Backslash escapes require an escaping string mode.');
        $end = 0;
        foreach ($escapes as $offset => $escape) {
            Check::input($offset >= $end, 'String escapes have ordered, nonoverlapping decoded-byte offsets.');
            $end = $offset + strlen($escape->bytes() ?? 'x');
        }
        $this->escapes = $escapes;
    }

    /**
     * Checks that each escape denotes the bytes at its offset.
     */
    public function validate(string $value): void
    {
        foreach ($this->escapes as $offset => $escape) {
            $bytes = $escape->bytes();
            Check::input(isset($value[$offset]), 'An escape names a byte in the decoded string.');
            Check::input($bytes === null ? Escape::tryFrom($value[$offset]) === null : substr($value, $offset, strlen($bytes)) === $bytes, 'An escape must decode to the string bytes at its offset.');
        }
    }

    /**
     * Reconstructs the chosen spelling solely from decoded bytes and escape kinds.
     */
    public function spell(string $value): string
    {
        $this->validate($value);
        $quote = $this->doubleQuoted ? '"' : "'";
        $result = $quote;
        for ($offset = 0; $offset < strlen($value); $offset++) {
            $escape = $this->escapes[$offset] ?? null;
            if ($escape !== null) {
                $result .= '\\' . ($escape === Escape::Discarded ? $value[$offset] : $escape->value);
                $offset += strlen($escape->bytes() ?? 'x') - 1;
            } else {
                $byte = $value[$offset];
                $result .= $byte === $quote ? $quote . $quote : ($byte === '\\' && $this->backslashEscapes ? '\\\\' : $byte);
            }
        }
        return $result . $quote;
    }
}
