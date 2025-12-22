<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Fake CastRenderer that produces generic CAST expressions.
 */
final class FakeCastRenderer implements CastRenderer
{
    public function renderCast(string $expression, ColumnType $type): string
    {
        return sprintf('CAST(%s AS %s)', $expression, $this->mapType($type));
    }

    public function renderNullCast(ColumnType $type): string
    {
        return sprintf('CAST(NULL AS %s)', $this->mapType($type));
    }

    private function mapType(ColumnType $type): string
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
