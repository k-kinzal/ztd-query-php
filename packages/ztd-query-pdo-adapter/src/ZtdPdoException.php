<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDOException;
use Throwable;

/**
 * ZTD-specific exception that extends PDOException.
 *
 * This allows users to catch either PDOException or ZtdPdoException
 * for ZTD-specific errors while maintaining compatibility with
 * existing PDO exception handling code.
 */
class ZtdPdoException extends PDOException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
