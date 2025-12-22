<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ErrorClassifier;
use ZtdQuery\Connection\Exception\DatabaseException;

/**
 * MySQL-specific error classifier.
 *
 * Classifies MySQL error codes to determine the type of error.
 */
final class MySqlErrorClassifier implements ErrorClassifier
{
    /**
     * MySQL error code: Unknown column.
     */
    private const ERROR_UNKNOWN_COLUMN = 1054;

    /**
     * MySQL error code: Table doesn't exist.
     */
    private const ERROR_TABLE_NOT_EXISTS = 1146;

    /**
     * MySQL error code: Undeclared variable.
     */
    private const ERROR_UNDECLARED_VARIABLE = 1327;

    /**
     * {@inheritDoc}
     */
    public function isUnknownSchemaError(DatabaseException $e): bool
    {
        $code = $e->getDriverErrorCode();
        if ($code === null) {
            return false;
        }

        return $code === self::ERROR_UNKNOWN_COLUMN
            || $code === self::ERROR_TABLE_NOT_EXISTS
            || $code === self::ERROR_UNDECLARED_VARIABLE;
    }
}
