<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use mysqli_sql_exception;
use Throwable;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\NotNullViolationException;

/**
 * Requires independent native and simulated failures to have the same known cause.
 */
final class FailureComparison
{
    /**
     * @throws OracleViolation When a wrapper hides a different or unknown failure.
     */
    public static function verify(Throwable $native, Throwable $simulated, string $sql): void
    {
        $expected = self::category($native);
        if ($expected === null || self::category($simulated) !== $expected) {
            throw new OracleViolation("Different native/ZTD failures\nSQL: $sql\nNative: {$native->getMessage()}\nZTD: {$simulated->getMessage()}", 0, $simulated);
        }
    }

    /**
     * A ZTD wrapper is never sufficient evidence of an expected rejection.
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
            if ($cause instanceof mysqli_sql_exception) {
                $category = match ($cause->getCode()) {
                    1062 => 'unique',
                    1048 => 'not-null',
                    1451, 1452 => 'foreign-key',
                    1064 => 'syntax',
                    1054 => 'column',
                    1146 => 'table',
                    default => null,
                };
                if ($category !== null) {
                    return $category;
                }
            }
        }

        return null;
    }
}
