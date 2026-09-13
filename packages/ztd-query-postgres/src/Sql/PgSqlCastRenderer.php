<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Postgres\Sql\CastTypeMapper;
use ZtdQuery\Schema\ColumnType;

/**
 * PostgreSQL CAST expression renderer.
 *
 * Maps ColumnType to PostgreSQL-specific CAST syntax.
 * Uses standard CAST() syntax (not :: shorthand) for maximum compatibility.
 */
final class PgSqlCastRenderer implements CastRenderer
{
    /**
     * {@inheritDoc}
     */
    public function renderCast(string $expression, ColumnType $type): string
    {
        $castType = (new CastTypeMapper())->map($type);

        return "CAST($expression AS $castType)";
    }

    /**
     * {@inheritDoc}
     */
    public function renderNullCast(ColumnType $type): string
    {
        $castType = (new CastTypeMapper())->map($type);

        return "CAST(NULL AS $castType)";
    }
}
