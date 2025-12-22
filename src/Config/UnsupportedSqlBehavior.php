<?php

declare(strict_types=1);

namespace ZtdQuery\Config;

/**
 * Defines how unsupported SQL statements should be handled.
 */
enum UnsupportedSqlBehavior: string
{
    /**
     * Silently ignore unsupported statements (default).
     * Use for production-like testing environments.
     */
    case Ignore = 'ignore';

    /**
     * Output a warning notice but continue execution.
     * Use for debugging during development.
     */
    case Notice = 'notice';

    /**
     * Throw an exception.
     * Use for strict testing and detecting unsupported SQL.
     */
    case Exception = 'exception';
}
