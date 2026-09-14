<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Validation;

/**
 * Validates and normalizes a standalone plan table name.
 *
 * @visibility root
 */
final class TableName
{
    /**
     * Returns assert table name.
     * @throws \SqlFixture\Plan\Exception\InvalidTableNameException
     */
    public function assertTableName(string $part): string
    {
        $table = trim($part);

        if (preg_match('/^(?:`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)$/', $table) !== 1) {
            throw new \SqlFixture\Plan\Exception\InvalidTableNameException($part);
        }

        return trim($table, '`"');
    }
}
