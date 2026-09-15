<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use Traversable;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * Declares the legacy PostgreSQL COPY entry points implemented by the adapter.
 *
 * These driver-specific methods need an explicit contract because PDO itself
 * does not declare them when PHP checks implementation attributes.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
interface PostgreSqlCopyMethods
{
    /**
     * Exports encoded rows from a table.
     *
     * @return list<string>|false
     * @throws ZtdPdoException
     */
    public function pgsqlCopyToArray(
        string $tableName,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): array|false;

    /**
     * Imports encoded rows into a table.
     *
     * @param array<string>|Traversable<array-key, string> $rows
     * @throws ZtdPdoException
     */
    public function pgsqlCopyFromArray(
        string $tableName,
        array|Traversable $rows,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool;

    /**
     * Exports encoded rows to a file.
     *
     * @throws ZtdPdoException
     */
    public function pgsqlCopyToFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool;

    /**
     * Imports encoded rows from a file.
     *
     * @throws ZtdPdoException
     */
    public function pgsqlCopyFromFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool;
}
