<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

/**
 * Reads the outer CREATE TABLE syntax.
 *
 * @visibility root
 */
final class TableSyntax
{
    /**
     * Normalizes the outer SQL text before parsing its declarations.
     */
    public function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = (string) preg_replace('/\/\*.*?\*\//s', '', (string) $sql);

        $result = preg_replace('/\s+/', ' ', trim($sql));

        return $result !== null ? $result : '';
    }

    /**
     * Reads the declared table identifier.
     */
    public function extractTableName(string $sql): ?string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"?(\w+)"?\.)?"?(\w+)"?\s*\(/i', $sql, $matches) === 1) {
            return $matches[2];
        }
        return null;
    }

    /**
     * Returns the parenthesized table definition body.
     */
    public function extractColumnsBlock(string $sql): ?string
    {
        $start = strpos($sql, '(');
        $end = strrpos($sql, ')');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($sql, $start + 1, $end - $start - 1);
    }

    /**
     * @return list<string>
     */
    public function extractTablePrimaryKeys(string $columnsBlock): array
    {
        $primaryKeys = [];

        if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $columnsBlock, $matches) === 1) {
            $columns = explode(',', $matches[1]);
            foreach ($columns as $col) {
                $col = trim($col);
                $col = trim($col, '"');
                if ($col !== '') {
                    $primaryKeys[] = $col;
                }
            }
        }

        return $primaryKeys;
    }
}
