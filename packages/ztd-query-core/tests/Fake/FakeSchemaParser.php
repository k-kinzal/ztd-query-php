<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;

/**
 * Fake SchemaParser that parses simplified CREATE TABLE statements.
 *
 * Supports a subset of SQL DDL via regex parsing:
 *   CREATE TABLE table_name (col1 TYPE [NOT NULL] [PRIMARY KEY], ..., PRIMARY KEY(col1, ...))
 */
final class FakeSchemaParser implements SchemaParser
{
    public function parse(string $createTableSql): ?TableDefinition
    {
        $sql = trim($createTableSql);

        if (preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\']?(\w+)[`"\']?\s*\((.+)\)\s*;?\s*$/is', $sql, $matches) !== 1) {
            return null;
        }

        $body = $matches[2];

        $columns = [];
        $columnTypes = [];
        $typedColumns = [];
        $primaryKeys = [];
        $notNullColumns = [];
        $uniqueConstraints = [];

        $parts = $this->splitColumns($body);

        foreach ($parts as $part) {
            $part = trim($part);

            if (preg_match('/^\s*PRIMARY\s+KEY\s*\(([^)]+)\)/i', $part, $pkMatch) === 1) {
                $pkCols = array_map(
                    static fn (string $c): string => trim($c, " \t\n\r\0\x0B`\"'"),
                    explode(',', $pkMatch[1])
                );
                $primaryKeys = array_merge($primaryKeys, $pkCols);
                continue;
            }

            if (preg_match('/^\s*(?:CONSTRAINT\s+[`"\']?(\w+)[`"\']?\s+)?UNIQUE\s*(?:KEY\s*)?(?:[`"\']?\w+[`"\']?\s*)?\(([^)]+)\)/i', $part, $uqMatch) === 1) {
                $constraintName = $uqMatch[1] !== '' ? $uqMatch[1] : 'unique_' . count($uniqueConstraints);
                $uqCols = array_map(
                    static fn (string $c): string => trim($c, " \t\n\r\0\x0B`\"'"),
                    explode(',', $uqMatch[2])
                );
                $uniqueConstraints[$constraintName] = $uqCols;
                continue;
            }

            if (preg_match('/^\s*(?:KEY|INDEX)\s/i', $part) === 1) {
                continue;
            }

            if (preg_match('/^\s*[`"\']?(\w+)[`"\']?\s+(\w+(?:\([^)]*\))?)/i', $part, $colMatch) === 1) {
                $colName = $colMatch[1];
                $colType = strtoupper($colMatch[2]);

                $columns[] = $colName;
                $columnTypes[$colName] = $colType;
                $typedColumns[$colName] = new ColumnType(
                    $this->mapTypeFamily($colType),
                    $colType
                );

                if (preg_match('/\bNOT\s+NULL\b/i', $part) === 1) {
                    $notNullColumns[] = $colName;
                }

                if (preg_match('/\bPRIMARY\s+KEY\b/i', $part) === 1) {
                    $primaryKeys[] = $colName;
                    if (!in_array($colName, $notNullColumns, true)) {
                        $notNullColumns[] = $colName;
                    }
                }

                if (preg_match('/\bUNIQUE\b/i', $part) === 1 && preg_match('/\bUNIQUE\s+KEY\b/i', $part) !== 1) {
                    $uniqueConstraints['unique_' . $colName] = [$colName];
                }
            }
        }

        if ($columns === []) {
            return null;
        }

        return new TableDefinition(
            $columns,
            $columnTypes,
            $primaryKeys,
            $notNullColumns,
            $uniqueConstraints,
            $typedColumns,
        );
    }

    private function mapTypeFamily(string $type): ColumnTypeFamily
    {
        $base = preg_replace('/\(.*\)/', '', $type) ?? $type;
        $base = strtoupper(trim($base));

        return match (true) {
            in_array($base, ['INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'SERIAL'], true) => ColumnTypeFamily::INTEGER,
            in_array($base, ['FLOAT', 'REAL'], true) => ColumnTypeFamily::FLOAT,
            in_array($base, ['DOUBLE'], true) => ColumnTypeFamily::DOUBLE,
            in_array($base, ['DECIMAL', 'NUMERIC', 'DEC'], true) => ColumnTypeFamily::DECIMAL,
            in_array($base, ['VARCHAR', 'CHAR', 'NVARCHAR', 'NCHAR'], true) => ColumnTypeFamily::STRING,
            in_array($base, ['TEXT', 'LONGTEXT', 'MEDIUMTEXT', 'TINYTEXT', 'CLOB'], true) => ColumnTypeFamily::TEXT,
            in_array($base, ['BOOLEAN', 'BOOL'], true) => ColumnTypeFamily::BOOLEAN,
            $base === 'DATE' => ColumnTypeFamily::DATE,
            $base === 'TIME' => ColumnTypeFamily::TIME,
            $base === 'DATETIME' => ColumnTypeFamily::DATETIME,
            $base === 'TIMESTAMP' => ColumnTypeFamily::TIMESTAMP,
            in_array($base, ['BLOB', 'BINARY', 'VARBINARY', 'LONGBLOB', 'MEDIUMBLOB', 'TINYBLOB'], true) => ColumnTypeFamily::BINARY,
            $base === 'JSON' => ColumnTypeFamily::JSON,
            default => ColumnTypeFamily::UNKNOWN,
        };
    }

    /**
     * Split column definitions by commas, respecting parentheses nesting.
     *
     * @return array<int, string>
     */
    private function splitColumns(string $body): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($body); $i++) {
            $ch = $body[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
