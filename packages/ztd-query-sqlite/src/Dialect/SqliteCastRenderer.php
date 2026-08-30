<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Dialect;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * SQLite implementation of CastRenderer.
 *
 * Maps ColumnDeclaration to SQLite CAST syntax using SQLite's type affinity system.
 * SQLite supports: INTEGER, REAL, TEXT, BLOB, NUMERIC.
 */
final class SqliteCastRenderer implements CastRenderer
{
    /**
     * Writes cast.
     *
     * @param string $expression
     * @param ColumnDeclaration $type
     * @return string
     */
    public function renderCast(string $expression, ColumnDeclaration $type): string
    {
        $castType = $this->mapToCastType($type);

        return "CAST($expression AS $castType)";
    }

    /**
     * Writes null cast.
     *
     * @param ColumnDeclaration $type
     * @return string
     */
    public function renderNullCast(ColumnDeclaration $type): string
    {
        $castType = $this->mapToCastType($type);

        return "CAST(NULL AS $castType)";
    }

    /**
     * Answers what a cast calls a column of this type.
     *
     * @param ColumnDeclaration $type How the column was declared
     *
     * @return string What it answers
     */
    public function mapToCastType(ColumnDeclaration $type): string
    {
        return match ($type->family) {
            ColumnTypeFamily::INTEGER => 'INTEGER',
            ColumnTypeFamily::DECIMAL => 'NUMERIC',
            ColumnTypeFamily::FLOAT, ColumnTypeFamily::DOUBLE => 'REAL',
            ColumnTypeFamily::BOOLEAN => 'INTEGER',
            ColumnTypeFamily::DATE, ColumnTypeFamily::TIME, ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'TEXT',
            ColumnTypeFamily::JSON => 'TEXT',
            ColumnTypeFamily::BINARY => 'BLOB',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::UNKNOWN => $this->mapNativeTypeToCastType($type->nativeType),
        };
    }

    /**
     * Answers what a cast calls a type ZTD could not place in a family.
     *
     * @param string $nativeType The native type
     *
     * @return string What it answers
     */
    public function mapNativeTypeToCastType(string $nativeType): string
    {
        $upperType = strtoupper($nativeType);
        $baseType = (string) preg_replace('/\(.*\)/', '', $upperType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'BOOLEAN', 'BOOL' => 'INTEGER',
            'REAL', 'DOUBLE', 'FLOAT' => 'REAL',
            'DECIMAL', 'NUMERIC' => 'NUMERIC',
            'BLOB' => 'BLOB',
            default => 'TEXT',
        };
    }
}
