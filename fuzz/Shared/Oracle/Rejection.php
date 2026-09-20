<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

use PDOException;
use Throwable;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Only deliberately invalid input cases may be rejected, for their declared reason.
 */
final class Rejection
{
    /**
     * Require the error category declared before executing the input.
     * @throws Finding
     */
    public static function verify(?Throwable $failure, string $expected): void
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if (($expected === 'unique' && $cause instanceof DuplicateKeyException)
                || ($expected === 'not-null' && $cause instanceof NotNullViolationException)
                || ($expected === 'table' && $cause instanceof UnknownSchemaException)
                || ($expected === 'unsupported' && $cause instanceof UnsupportedSqlException)) {
                return;
            }
            if ($cause instanceof PDOException) {
                $state = $cause->errorInfo[0] ?? null;
                $code = $cause->errorInfo[1] ?? null;
                $matches = match ($expected) {
                    'unique' => $state === '23505' || $code === 1062 || str_contains($cause->getMessage(), 'UNIQUE constraint failed'),
                    'not-null' => $state === '23502' || $code === 1048 || str_contains($cause->getMessage(), 'NOT NULL constraint failed'),
                    'table' => $state === '42P01' || $code === 1146 || str_contains($cause->getMessage(), 'no such table:'),
                    default => false,
                };
                if ($matches) {
                    return;
                }
            }
        }
        throw new Finding('Expected ' . $expected . ' rejection, got ' . ($failure === null ? 'success' : $failure::class . ': ' . $failure->getMessage()), 0, $failure);
    }
}
