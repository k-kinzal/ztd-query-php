<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Fake CastRenderer that produces generic CAST expressions.
 */
final class FakeCastRenderer implements CastRenderer
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
        return sprintf('CAST(%s AS %s)', $expression, $this->mapType($type));
    }

    /**
     * Writes null cast.
     *
     * @param ColumnDeclaration $type
     * @return string
     */
    public function renderNullCast(ColumnDeclaration $type): string
    {
        return sprintf('CAST(NULL AS %s)', $this->mapType($type));
    }

    /**
     * Map type.
     *
     * @param ColumnDeclaration $type
     * @return string
     */
    public function mapType(ColumnDeclaration $type): string
    {
        return match ($type->family) {
            ColumnTypeFamily::INTEGER => 'INTEGER',
            ColumnTypeFamily::FLOAT => 'REAL',
            ColumnTypeFamily::DOUBLE => 'REAL',
            ColumnTypeFamily::DECIMAL => 'NUMERIC',
            ColumnTypeFamily::STRING => 'TEXT',
            ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::BOOLEAN => 'INTEGER',
            ColumnTypeFamily::DATE => 'TEXT',
            ColumnTypeFamily::TIME => 'TEXT',
            ColumnTypeFamily::DATETIME => 'TEXT',
            ColumnTypeFamily::TIMESTAMP => 'TEXT',
            ColumnTypeFamily::BINARY => 'BLOB',
            ColumnTypeFamily::JSON => 'TEXT',
            ColumnTypeFamily::UNKNOWN => $type->nativeType !== '' ? $type->nativeType : 'TEXT',
        };
    }
}
