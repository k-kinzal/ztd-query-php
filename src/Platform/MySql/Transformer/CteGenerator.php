<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

/**
 * Generates MySQL-compatible CTE fragments for shadowed tables.
 */
class CteGenerator
{
    /**
     * @param string $tableName
     * @param array<int, array<string, mixed>> $rows
     * @param string[]|null $columns
     * @param array<string, string>|null $columnTypes Map of column name to MySQL type string.
     * @return string
     */
    public function generate(
        string $tableName,
        array $rows,
        ?array $columns = null,
        ?array $columnTypes = null
    ): string {
        if ($columns !== null) {
            if ($rows === []) {
                $selects = [];
                foreach ($columns as $col) {
                    $castType = $this->getCastTypeForNull($columnTypes[$col] ?? null);
                    $selects[] = "CAST(NULL AS $castType) AS `$col`";
                }
                return "`$tableName` AS (SELECT " . implode(", ", $selects) . " FROM DUAL WHERE 0)";
            }

            $ctes = [];
            foreach ($rows as $row) {
                $selects = [];
                foreach ($columns as $col) {
                    $mysqlType = $columnTypes[$col] ?? null;
                    $valStr = $this->formatValue($row[$col] ?? null, $mysqlType);
                    $selects[] = "$valStr AS `$col`";
                }
                $ctes[] = "SELECT " . implode(", ", $selects);
            }

            $union = implode(" UNION ALL ", $ctes);
            return "`$tableName` AS ($union)";
        }

        if ($rows === []) {
            throw new \RuntimeException("Cannot shadow table '$tableName' with empty data (columns unknown).");
        }

        $ctes = [];
        foreach ($rows as $row) {
            $selects = [];
            foreach ($row as $col => $val) {
                $colName = (string) $col;
                $mysqlType = $columnTypes[$colName] ?? null;
                $valStr = $this->formatValue($val, $mysqlType);
                $selects[] = "$valStr AS `$colName`";
            }
            $ctes[] = "SELECT " . implode(", ", $selects);
        }

        $union = implode(" UNION ALL ", $ctes);
        return "`$tableName` AS ($union)";
    }

    private function formatValue(mixed $val, ?string $mysqlType = null): string
    {
        if (is_null($val)) {
            return "NULL";
        }

        if ($mysqlType !== null) {
            return $this->formatWithMysqlType($val, $mysqlType);
        }

        // Fallback to PHP type-based casting when MySQL type is unknown
        if (is_int($val)) {
            return "CAST($val AS SIGNED)";
        }
        if (is_string($val)) {
            return "CAST(" . $this->quote($val) . " AS CHAR)";
        }
        if (is_bool($val)) {
            return $val ? "TRUE" : "FALSE";
        }
        if (is_float($val)) {
            return (string) $val;
        }
        if (is_object($val) && method_exists($val, '__toString')) {
            return (string) $val;
        }
        throw new \RuntimeException('Unsupported value type for CTE shadowing.');
    }

    private function formatWithMysqlType(mixed $val, string $mysqlType): string
    {
        $castType = $this->mapMysqlTypeToCastType($mysqlType);
        $strVal = is_scalar($val) ? (string) $val : ($val === null ? '' : serialize($val));
        $quotedVal = $this->quote($strVal);

        return "CAST($quotedVal AS $castType)";
    }

    /**
     * Map MySQL column type to CAST type.
     *
     * MySQL CAST supports: BINARY, CHAR, DATE, DATETIME, DECIMAL, DOUBLE,
     * FLOAT, JSON, NCHAR, REAL, SIGNED, TIME, UNSIGNED, YEAR.
     */
    private function mapMysqlTypeToCastType(string $mysqlType): string
    {
        $upperType = strtoupper($mysqlType);
        $baseType = preg_replace('/\(.*\)/', '', $upperType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' => 'SIGNED',
            'DECIMAL', 'NUMERIC' => $this->extractDecimalType($upperType),
            'FLOAT' => 'FLOAT',
            'DOUBLE', 'REAL' => 'DOUBLE',
            'DATE' => 'DATE',
            'DATETIME', 'TIMESTAMP' => 'DATETIME',
            'TIME' => 'TIME',
            'YEAR' => 'YEAR',
            'JSON' => 'JSON',
            'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB' => 'BINARY',
            default => 'CHAR',
        };
    }

    /**
     * Extract DECIMAL(p,s) from type string, defaulting to DECIMAL(65,30).
     */
    private function extractDecimalType(string $upperType): string
    {
        if (preg_match('/DECIMAL\((\d+),(\d+)\)/', $upperType, $matches)) {
            return "DECIMAL({$matches[1]},{$matches[2]})";
        }
        if (preg_match('/DECIMAL\((\d+)\)/', $upperType, $matches)) {
            return "DECIMAL({$matches[1]},0)";
        }
        // Default precision/scale for DECIMAL without parameters
        return 'DECIMAL(65,30)';
    }

    /**
     * Get cast type for NULL values in empty row sets.
     */
    private function getCastTypeForNull(?string $mysqlType): string
    {
        if ($mysqlType === null) {
            return 'CHAR';
        }
        return $this->mapMysqlTypeToCastType($mysqlType);
    }

    private function quote(string $val): string
    {
        return "'" . str_replace("'", "''", $val) . "'";
    }
}
