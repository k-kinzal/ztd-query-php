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
 * @visibility public
 * @example Catch ZTD failures as PDO exceptions
 *     $error = new \ZtdQuery\Adapter\Pdo\ZtdPdoException('Cannot simulate this statement');
 *     $error instanceof \PDOException // => true
 */
class ZtdPdoException extends PDOException
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $message
     * @param int $code
     * @param ?Throwable $previous
     * @visibility public
     * @example Preserve the original database failure
     *     $cause = new \RuntimeException('Database failure');
     *     $error = new \ZtdQuery\Adapter\Pdo\ZtdPdoException('Simulation failed', 7, $cause);
     *     $error->getMessage() // => 'Simulation failed'
     *     $error->getCode() // => 7
     *     $error->getPrevious() === $cause // => true
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
