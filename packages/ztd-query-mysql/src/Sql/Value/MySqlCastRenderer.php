<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Sql\Value;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * MySQL implementation of CastRenderer.
 *
 * Maps ColumnDeclaration to MySQL CAST syntax (e.g. CAST(expr AS SIGNED), CAST(expr AS CHAR)).
 */
final class MySqlCastRenderer implements CastRenderer
{
    /**
     * Render Cast for the supplied MySQL input.
     */
    public function renderCast(string $expression, ColumnDeclaration $type): string
    {
        if ($type->family === ColumnTypeFamily::INTEGER && in_array(strtoupper($type->nativeType), ['YEAR', 'YEAR(4)'], true)) {
            return "CAST(CAST($expression AS YEAR) AS SIGNED)";
        }

        $castType = (match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => (new CastTypeResolver())->extractDecimalCast($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => (new CastTypeResolver())->mapNativeTypeToCastType($type->nativeType),
        });

        return "CAST($expression AS $castType)";
    }

    /**
     * Render Null Cast for the supplied MySQL input.
     */
    public function renderNullCast(ColumnDeclaration $type): string
    {
        $castType = (match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => (new CastTypeResolver())->extractDecimalCast($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => (new CastTypeResolver())->mapNativeTypeToCastType($type->nativeType),
        });

        return "CAST(NULL AS $castType)";
    }

}
