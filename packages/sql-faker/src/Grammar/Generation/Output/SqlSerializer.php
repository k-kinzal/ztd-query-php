<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Output;

/**
 * Concatenates resolved output without formatting or interpreting SQL.
 */
final class SqlSerializer
{
    /**
     * @param list<string> $pieces
     */
    public function serialize(array $pieces): string
    {
        return implode('', $pieces);
    }
}
