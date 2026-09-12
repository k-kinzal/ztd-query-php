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
 *
 * @visibility public
 * @example Keep the underlying error available to callers
 *     $cause = new \RuntimeException('Unknown table');
 *     $error = new \ZtdQuery\Adapter\Mysqli\ZtdMysqliException('Rewrite failed', 7, $cause);
 *     $error->getMessage() // => 'Rewrite failed'
 *     $error->getCode() // => 7
 *     $error->getPrevious() === $cause // => true
 */
class ZtdMysqliException extends RuntimeException
{
    /**
     * Preserve the original failure as the previous exception.
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
