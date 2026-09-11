<?php

declare(strict_types=1);

namespace Tests\Fixtures\SqlFaker;

use Closure;
use ErrorException;

/**
 * Fails scanner boundary checks immediately when PHP reports an invalid byte access.
 */
final class PhpDiagnosticGuard
{
    /**
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public static function run(Closure $operation): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
