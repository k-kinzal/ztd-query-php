<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;

/**
 * Derives the published UTF-8 heading from a decoded result alias.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AliasHeading
{
    /**
     * Keeps a complete UTF-8 prefix of the server's trimmed alias.
     */
    public function normalize(string $requested): string
    {
        Check::input(
            preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]*\z/u', $requested) === 1,
            'A requested MySQL result alias needs BMP characters without NUL.'
        );
        $prefix = (new NativeLocalNames())->prefix($requested);
        while (preg_match('//u', $prefix) !== 1) {
            $prefix = substr($prefix, 0, -1);
        }

        return $prefix;
    }
}
