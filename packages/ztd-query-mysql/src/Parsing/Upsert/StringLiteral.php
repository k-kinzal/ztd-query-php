<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Upsert;

/**
 * String Literal.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class StringLiteral
{
    /**
     * String for the supplied MySQL input.
     */
    public function string(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return str_replace(["''", "\\'", '\\\\'], ["'", "'", '\\'], $inner);
    }
}
