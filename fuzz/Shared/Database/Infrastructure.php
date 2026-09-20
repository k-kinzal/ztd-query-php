<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use PDOException;
use Throwable;

/**
 * Distinguishes an unavailable fuzz database from a behavioral finding.
 */
final class Infrastructure
{
    /**
     * Fail the runner on a lost connection instead of recording a SQL finding.
     */
    public static function check(Throwable $failure): void
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            $state = $cause instanceof PDOException ? ($cause->errorInfo[0] ?? null) : null;
            $transportFailure = $cause instanceof PDOException && ($cause->errorInfo[1] ?? null) === 7
                && (str_contains($cause->getMessage(), 'no connection to the server')
                    || str_contains($cause->getMessage(), 'server closed the connection unexpectedly'));
            if ($cause instanceof PDOException && ($transportFailure
                || (is_string($state) && str_starts_with($state, '08'))
                || in_array($state, ['57P01', '57P02', '57P03'], true)
                || in_array($cause->errorInfo[1] ?? null, [2002, 2006, 2013], true))) {
                fwrite(STDERR, 'Fuzz database connection failed: ' . $cause->getMessage() . PHP_EOL);
                exit(2);
            }
        }
    }
}
