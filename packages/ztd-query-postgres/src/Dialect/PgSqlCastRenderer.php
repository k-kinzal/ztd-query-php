<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Dialect;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * PostgreSQL CAST expression renderer.
 *
 * Maps ColumnDeclaration to PostgreSQL-specific CAST syntax.
 * Uses standard CAST() syntax (not :: shorthand) for maximum compatibility.
 */
final class PgSqlCastRenderer implements CastRenderer
{
    /**
     * {@inheritDoc}
     */
    public function renderCast(string $expression, ColumnDeclaration $type): string
    {
        $castType = $this->mapToCastType($type);

        return "CAST($expression AS $castType)";
    }

    /**
     * {@inheritDoc}
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
        $nativeType = trim($type->nativeType);
        if (str_ends_with($nativeType, '[]')) {
            return $nativeType;
        }

        if ($type->family === ColumnTypeFamily::UNKNOWN) {
            return $nativeType !== '' ? $nativeType : 'TEXT';
        }

        return match ($type->family) {
            ColumnTypeFamily::INTEGER => $this->mapIntegerType($nativeType),
            ColumnTypeFamily::FLOAT => 'REAL',
            ColumnTypeFamily::DOUBLE => 'DOUBLE PRECISION',
            ColumnTypeFamily::DECIMAL => $this->extractDecimalType($type->nativeType),
            ColumnTypeFamily::STRING => $this->extractStringType($type->nativeType),
            ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::BOOLEAN => 'BOOLEAN',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::DATETIME => 'TIMESTAMP',
            ColumnTypeFamily::TIMESTAMP => 'TIMESTAMP',
            ColumnTypeFamily::BINARY => 'BYTEA',
            ColumnTypeFamily::JSON => 'JSONB',
        };
    }

    /**
     * Answers which of PostgreSQL's integer types a declaration means.
     *
     * @param string $nativeType The native type
     *
     * @return string What it answers
     */
    public function mapIntegerType(string $nativeType): string
    {
        $baseType = strtoupper((string) preg_replace('/\(.*\)/', '', $nativeType));

        return match ($baseType) {
            'INT2', 'SMALLINT', 'SMALLSERIAL' => 'SMALLINT',
            'INT8', 'BIGINT', 'BIGSERIAL' => 'BIGINT',
            default => 'INTEGER',
        };
    }

    /**
     * Answers the NUMERIC a cast would keep the same digits with.
     *
     * @param string $nativeType The native type
     *
     * @return string What it answers
     */
    public function extractDecimalType(string $nativeType): string
    {
        $upper = strtoupper($nativeType);
        if (preg_match('/(?:DECIMAL|NUMERIC)\((\d+),(\d+)\)/', $upper, $matches) === 1) {
            return "NUMERIC({$matches[1]},{$matches[2]})";
        }
        if (preg_match('/(?:DECIMAL|NUMERIC)\((\d+)\)/', $upper, $matches) === 1) {
            return "NUMERIC({$matches[1]},0)";
        }

        return 'NUMERIC';
    }

    /**
     * Answers the character type a cast would keep the same width with.
     *
     * @param string $nativeType The native type
     *
     * @return string What it answers
     */
    public function extractStringType(string $nativeType): string
    {
        $upper = strtoupper($nativeType);
        if (preg_match('/VARCHAR\((\d+)\)/', $upper, $matches) === 1) {
            return "VARCHAR({$matches[1]})";
        }

        if ($upper === 'VARCHAR' || $upper === 'CHARACTER VARYING') {
            return 'VARCHAR';
        }

        return 'TEXT';
    }
}
