<?php

declare(strict_types=1);

namespace ZtdQuery\Connection\Exception;

use RuntimeException;
use Throwable;

/**
 * Database exception that wraps driver-specific errors.
 *
 * This exception provides a unified interface for handling database errors
 * from different drivers (PDO, mysqli, SQLite3).
 */
class DatabaseException extends RuntimeException
{
    /**
     * Driver-specific error code.
     */
    private ?int $driverErrorCode;

    /**
     * @param string $message Exception message.
     * @param int|null $driverErrorCode Driver-specific error code.
     * @param int $code Exception code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        ?int $driverErrorCode = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->driverErrorCode = $driverErrorCode;
    }

    /**
     * Get the driver-specific error code.
     */
    public function getDriverErrorCode(): ?int
    {
        return $this->driverErrorCode;
    }
}
