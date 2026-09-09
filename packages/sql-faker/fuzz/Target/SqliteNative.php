<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use FFI;
use FFI\CData;

/**
 * Validates dynamically bound SQLite FFI calls at the native API boundary.
 * @see https://www.php.net/manual/en/ffi.cdef.php
 */
final class SqliteNative
{
    private readonly FFI $ffi;

    /**
     * Loads only the read/prepare/finalize API required by the oracle.
     */
    public function __construct(string $library)
    {
        $this->ffi = FFI::cdef('typedef struct sqlite3 sqlite3; typedef struct sqlite3_stmt sqlite3_stmt; const char *sqlite3_libversion(void); const char *sqlite3_compileoption_get(int); int sqlite3_open(const char *, sqlite3 **); int sqlite3_close(sqlite3 *); int sqlite3_prepare_v3(sqlite3 *, const char *, int, unsigned int, sqlite3_stmt **, const char **); int sqlite3_finalize(sqlite3_stmt *);', $library);
    }

    /**
     * @throws InfrastructureFailure When native allocation fails
     */
    public function allocate(string $type): CData
    {
        return $this->ffi->new($type) ?? throw new InfrastructureFailure('Cannot allocate SQLite parser memory.');
    }

    /**
     * @param int|string|CData ...$arguments
     * @throws InfrastructureFailure When a native status result has the wrong type
     */
    public function integer(string $function, int|string|CData ...$arguments): int
    {
        $result = $this->invoke($function, array_values($arguments));
        return is_int($result) ? $result : throw new InfrastructureFailure('Invalid SQLite integer result: ' . $function);
    }

    /**
     * @param int|string|CData ...$arguments
     * @throws InfrastructureFailure When a native text result has the wrong type
     */
    public function text(string $function, int|string|CData ...$arguments): ?string
    {
        $result = $this->invoke($function, array_values($arguments));
        return is_string($result) || $result === null ? $result : throw new InfrastructureFailure('Invalid SQLite text result: ' . $function);
    }

    /**
     * Validates the dynamically exposed C function and its scalar result without asserting a fictitious PHP interface.
     * @param list<int|string|CData> $arguments
     * @throws InfrastructureFailure When a declared function or supported return value is unavailable
     */
    public function invoke(string $function, array $arguments): int|string|null
    {
        $callable = [$this->ffi, $function];
        if (!is_callable($callable)) {
            throw new InfrastructureFailure('Missing SQLite native function: ' . $function);
        }
        $result = $callable(...$arguments);
        if (!is_int($result) && !is_string($result) && $result !== null) {
            throw new InfrastructureFailure('Invalid SQLite native result: ' . $function);
        }
        return $result;
    }
}
