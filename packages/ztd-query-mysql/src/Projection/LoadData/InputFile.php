<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Reads an explicitly named local LOAD DATA file before projecting its rows.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class InputFile
{
    /**
     * Reject missing, non-file and unreadable LOAD DATA sources.
     *
     * @throws UnsupportedSqlException
     */
    public function read(LoadStatement $statement, string $sql): string
    {
        if ($statement->file_name === null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $fileProperties = get_object_vars($statement->file_name);
        $path = $fileProperties['file'] ?? null;
        if (!is_string($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if ($path === '') {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if (!is_file($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if (!is_readable($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file could not be read');
        }

        return $contents;
    }
}
