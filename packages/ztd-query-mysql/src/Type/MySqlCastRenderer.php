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
    /**
     * Render Cast for the supplied MySQL input.
     */
    public function renderCast(string $expression, ColumnType $type): string
    {
        $castType = (match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => (new Type\CastTypeResolver())->extractDecimalCast($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => (new Type\CastTypeResolver())->mapNativeTypeToCastType($type->nativeType),
        });

        return "CAST($expression AS $castType)";
    }

    /**
     * Render Null Cast for the supplied MySQL input.
     */
    public function renderNullCast(ColumnType $type): string
    {
        $castType = (match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => (new Type\CastTypeResolver())->extractDecimalCast($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => (new Type\CastTypeResolver())->mapNativeTypeToCastType($type->nativeType),
        });

        return "CAST(NULL AS $castType)";
    }

}
