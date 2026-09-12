<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Validation;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * Validates and normalizes a standalone plan table name.
 *
 * @visibility root
 */
final class TableName
{
    /**
     * Returns assert table name.
     * @throws PlanSyntaxException
     */
    public function assertTableName(string $part): string
    {
        $table = trim($part);

        if (preg_match('/^(?:`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)$/', $table) !== 1) {
            throw PlanSyntaxException::notATableName($part);
        }

        return trim($table, '`"');
    }
}
