<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

/**
 * The catalog kind removed by a PostgreSQL view removal.
 *
 * @visibility public
 * @example Reading the native spelling
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewRemovalKind::Materialized->value // => 'MATERIALIZED VIEW'
 */
enum ViewRemovalKind: string
{
    case Ordinary = 'VIEW';
    case Materialized = 'MATERIALIZED VIEW';
}
