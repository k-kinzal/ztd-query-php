<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

/**
 * Exception thrown when an UPDATE result cannot be matched to a shadow row.
 */
final class MissingPrimaryKeyException extends SimulationException
{
    private string $tableName;

    /**
     * Binds the instance to what it will work from.
     *
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        parent::__construct("UPDATE simulation requires primary keys for '$tableName'.");
        $this->tableName = $tableName;
    }

    /**
     * Answers table name.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }
}
