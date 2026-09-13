<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parsing\Expression;

use ZtdQuery\Sql\SqlToken;

/**
 * Shares a cursor across recursive UPSERT grammar productions.
 *
 * @visibility root
 */
final class ExpressionCursor
{
    /**
     * Index of the next significant expression token.
     */
    public int $index = 0;

    /**
     * Retains the source and its significant tokens for syntax diagnostics.
     * @param list<SqlToken> $tokens
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $tableName,
        public readonly array $tokens,
    ) {
    }
}
