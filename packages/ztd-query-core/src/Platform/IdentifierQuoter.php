<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

/**
 * Quotes SQL identifiers using platform-specific quoting characters.
 *
 * MySQL uses backticks, PostgreSQL and SQLite use double quotes.
 */
interface IdentifierQuoter
{
    /**
     * Quote a SQL identifier.
     *
     * @param string $identifier The identifier to quote (e.g. "users", "column_name").
     * @return string The quoted identifier (e.g. "`users`", "\"users\"").
     */
    public function quote(string $identifier): string;
}
