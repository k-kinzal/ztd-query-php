<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when SQL parsing fails due to syntax errors.
 */
final class SqlParseException extends RuntimeException
{
    /**
     * The SQL statement that failed to parse.
     */
    private string $sql;

    /**
     * The parse error message.
     */
    private string $parseError;

    /**
     * @param string $sql The SQL statement that failed to parse.
     * @param string $parseError The parse error message.
     */
    public function __construct(string $sql, string $parseError)
    {
        parent::__construct(sprintf('SQL syntax error: %s', $parseError));
        $this->sql = $sql;
        $this->parseError = $parseError;
    }

    /**
     * Get the SQL statement that failed to parse.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the parse error message.
     */
    public function getParseError(): string
    {
        return $this->parseError;
    }
}
