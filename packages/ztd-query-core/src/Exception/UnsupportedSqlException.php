<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when an unsupported SQL statement is executed.
 */
final class UnsupportedSqlException extends RuntimeException
{
    /**
     * The unsupported SQL statement.
     */
    private string $sql;

    /**
     * The category of the unsupported SQL.
     */
    private string $category;

    /**
     * @param string $sql The unsupported SQL statement.
     * @param string $category The category of the unsupported SQL.
     */
    public function __construct(string $sql, string $category = 'Unsupported')
    {
        parent::__construct(sprintf('ZTD Write Protection: %s SQL statement.', $category));
        $this->sql = $sql;
        $this->category = $category;
    }

    /**
     * Get the unsupported SQL statement.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the category of the unsupported SQL.
     */
    public function getCategory(): string
    {
        return $this->category;
    }
}
