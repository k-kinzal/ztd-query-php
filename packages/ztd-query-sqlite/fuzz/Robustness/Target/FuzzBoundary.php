<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Closure;
use Composer\InstalledVersions;
use Error;
use PDO;
use Throwable;

/**
 * Translates unexpected target failures into reproducible PHP-Fuzzer findings.
 */
final class FuzzBoundary
{
    /**
     * Reports every unexpected failure with the exact input, SQL and runtime.
     *
     * @param Closure(): void $operation
     * @throws Error
     */
    public static function run(string $target, string $input, string $sql, Closure $operation): void
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            $version = (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()');
            throw new Error(sprintf(
                "Target: %s\nInput (hex): %s\nPHP: %s\nSQLite: %s\nSQLFaker: %s\nGrammar: sqlite-3.47.2\nSQL: %s\n%s: %s",
                $target,
                bin2hex($input),
                PHP_VERSION,
                $version === false ? 'unknown' : $version->fetchColumn(),
                InstalledVersions::getReference('k-kinzal/sql-faker') ?? 'unknown',
                $sql,
                $failure::class,
                $failure->getMessage(),
            ), 0, $failure);
        }
    }
}
