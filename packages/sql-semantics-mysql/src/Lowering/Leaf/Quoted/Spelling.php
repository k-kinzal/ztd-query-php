<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf\Quoted;

use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Escape;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation;

/**
 * Decodes delimiter and escape choices while the parser token is available.
 * Only typed choices and decoded-byte offsets survive lowering.
 * @visibility SqlSemantics\Platform\MySql
 */
final class Spelling
{
    /**
     * Reads a quoted or national string under the input escape setting.
     */
    public function read(string $token, bool $backslashEscapes): Notation
    {
        $start = in_array($token[0] ?? '', ['N', 'n'], true) ? 1 : 0;
        $quote = $token[$start] ?? "'";
        $escapes = [];
        $decoded = 0;
        for ($index = $start + 1; $index < strlen($token) - 1; $index++) {
            $byte = $token[$index];
            if ($byte === '\\' && $backslashEscapes && $index + 1 < strlen($token) - 1) {
                $escape = Escape::tryFrom($token[++$index]) ?? Escape::Discarded;
                $escapes[$decoded] = $escape;
                $decoded += strlen($escape->bytes() ?? 'x');
            } else {
                if ($byte === $quote && ($token[$index + 1] ?? '') === $quote) {
                    $index++;
                }
                $decoded++;
            }
        }
        return new Notation($quote === '"', $escapes, $backslashEscapes);
    }
}
