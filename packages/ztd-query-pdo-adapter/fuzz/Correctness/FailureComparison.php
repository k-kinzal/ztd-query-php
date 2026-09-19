<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use PDOException;
use Throwable;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\NotNullViolationException;

/**
 * Requires independently observed failures to describe the same database condition.
 */
final class FailureComparison
{
    /**
     * @throws OracleViolation When either failure has no comparable database cause or differs.
     */
    public static function verify(Throwable $native, Throwable $simulated, string $sql): void
    {
        $expected = self::category($native);
        $actual = self::category($simulated);
        if ($expected === null || $actual !== $expected) {
            throw new OracleViolation("Different native/ZTD failures\nSQL: $sql\nNative: {$native->getMessage()}\nZTD: {$simulated->getMessage()}", 0, $simulated);
        }
    }

    /**
     * Unwrap adapter exceptions without treating the wrapper itself as an expected rejection.
     */
    public static function category(Throwable $failure): ?string
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof DuplicateKeyException) {
                return 'unique';
            }
            if ($cause instanceof NotNullViolationException) {
                return 'not-null';
            }
            if ($cause instanceof ForeignKeyViolationException) {
                return 'foreign-key';
            }
            if ($cause instanceof PDOException && $cause->errorInfo !== null) {
                $state = $cause->errorInfo[0];
                $code = $cause->errorInfo[1];
                if ($state === '23505' || $code === 1062 || str_contains($cause->getMessage(), 'UNIQUE constraint failed')) {
                    return 'unique';
                }
                if ($state === '23502' || $code === 1048 || str_contains($cause->getMessage(), 'NOT NULL constraint failed')) {
                    return 'not-null';
                }
                if ($state === '23503' || in_array($code, [1451, 1452], true) || str_contains($cause->getMessage(), 'FOREIGN KEY constraint failed')) {
                    return 'foreign-key';
                }
                if ($state === '42601' || $code === 1064 || str_contains($cause->getMessage(), 'syntax error')) {
                    return 'syntax';
                }
                if ($state === '42703' || $code === 1054 || str_contains($cause->getMessage(), 'no such column:')) {
                    return 'column';
                }
                if ($state === '42P01' || $code === 1146 || str_contains($cause->getMessage(), 'no such table:')) {
                    return 'table';
                }
            }
        }

        return null;
    }
}
