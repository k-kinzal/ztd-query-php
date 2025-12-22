<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * MySQL implementation of CastRenderer.
 *
 * Maps ColumnType to MySQL CAST syntax (e.g. CAST(expr AS SIGNED), CAST(expr AS CHAR)).
 */
final class MySqlCastRenderer implements CastRenderer
{
    public function renderCast(string $expression, ColumnType $type): string
    {
        $castType = $this->mapToCastType($type);

        return "CAST($expression AS $castType)";
    }

    public function renderNullCast(ColumnType $type): string
    {
        $castType = $this->mapToCastType($type);

        return "CAST(NULL AS $castType)";
    }

    private function mapToCastType(ColumnType $type): string
    {
        return match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => $this->extractDecimalCast($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => $this->mapNativeTypeToCastType($type->nativeType),
        };
    }

    private function extractDecimalCast(string $nativeType): string
    {
        $upper = strtoupper($nativeType);
        if (preg_match('/DECIMAL\((\d+),(\d+)\)/', $upper, $matches) === 1) {
            return "DECIMAL({$matches[1]},{$matches[2]})";
        }
        if (preg_match('/DECIMAL\((\d+)\)/', $upper, $matches) === 1) {
            return "DECIMAL({$matches[1]},0)";
        }

        return 'DECIMAL(65,30)';
    }

    /**
     * Fallback mapping for UNKNOWN family using native type string.
     */
    private function mapNativeTypeToCastType(string $nativeType): string
    {
        $upperType = strtoupper($nativeType);
        $baseType = (string) preg_replace('/\(.*\)/', '', $upperType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' => 'SIGNED',
            'DECIMAL', 'NUMERIC' => $this->extractDecimalCast($nativeType),
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
}
