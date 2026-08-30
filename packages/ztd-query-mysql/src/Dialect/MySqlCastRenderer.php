<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Dialect;

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
     * Writes cast.
     *
     * @param string $expression
     * @param ColumnDeclaration $type
     * @return string
     */
    public function renderCast(string $expression, ColumnDeclaration $type): string
    {
        $castType = $this->castTypeOf($type);

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
        $castType = $this->castTypeOf($type);

        return "CAST(NULL AS $castType)";
    }

    /**
     * Answers what MySQL's CAST calls a column of this type.
     *
     * CAST does not take the types a column is declared with -- there is no
     * CAST to VARCHAR -- so every declaration has to be answered by one of the
     * handful of names CAST does take.
     *
     * @param ColumnDeclaration $type How the column was declared
     *
     * @return string The name CAST would take
     */
    public function castTypeOf(ColumnDeclaration $type): string
    {
        return match ($type->family) {
            ColumnTypeFamily::INTEGER => 'SIGNED',
            ColumnTypeFamily::DECIMAL => $this->decimalCastOf($type->nativeType),
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::BOOLEAN => 'UNSIGNED',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::DATETIME, ColumnTypeFamily::TIMESTAMP => 'DATETIME',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::BINARY => 'BINARY',
            ColumnTypeFamily::STRING, ColumnTypeFamily::TEXT => 'CHAR',
            ColumnTypeFamily::UNKNOWN => $this->castTypeOfNative($type->nativeType),
        };
    }

    /**
     * Answers the DECIMAL a CAST would keep the same digits with.
     *
     * A declaration that says nothing about its digits is cast to the widest
     * DECIMAL MySQL has, so that nothing is rounded away by the cast itself.
     *
     * @param string $nativeType The declaration, as the platform wrote it
     *
     * @return string DECIMAL with the digits it keeps
     */
    public function decimalCastOf(string $nativeType): string
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
     * Answers what CAST calls a type ZTD could not place in a family.
     *
     * The declaration's own words are all there is to go on, so the width or
     * precision written after them is dropped and the word itself is read.
     *
     * @param string $nativeType The declaration, as the platform wrote it
     *
     * @return string The name CAST would take, and CHAR for anything unrecognised
     */
    public function castTypeOfNative(string $nativeType): string
    {
        $upperType = strtoupper($nativeType);
        $baseType = (string) preg_replace('/\(.*\)/', '', $upperType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' => 'SIGNED',
            'DECIMAL', 'NUMERIC' => $this->decimalCastOf($nativeType),
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
