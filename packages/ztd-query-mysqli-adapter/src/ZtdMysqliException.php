<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use RuntimeException;
use Throwable;

/**
 * ZTD-specific exception for mysqli adapter.
 *
 * Since mysqli_sql_exception is final, this extends RuntimeException.
 * Users should catch this exception for ZTD-specific errors in the mysqli adapter.
 */
class ZtdMysqliException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
