<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when a query references unknown tables or columns.
 */
final class UnknownSchemaException extends RuntimeException
{
    /**
     * The SQL statement that referenced unknown schema.
     */
    private string $sql;

    /**
     * The unknown identifier (table or column name).
     */
    private string $identifier;

    /**
     * The type of identifier ('table' or 'column').
     */
    private string $identifierType;

    /**
     * @param string $sql The SQL statement.
     * @param string $identifier The unknown identifier.
     * @param string $identifierType The type of identifier ('table' or 'column').
     */
    public function __construct(string $sql, string $identifier, string $identifierType = 'table')
    {
        parent::__construct(sprintf('Unknown %s: %s', $identifierType, $identifier));
        $this->sql = $sql;
        $this->identifier = $identifier;
        $this->identifierType = $identifierType;
    }

    /**
     * Get the SQL statement.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the unknown identifier.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the type of identifier.
     */
    public function getIdentifierType(): string
    {
        return $this->identifierType;
    }
}
