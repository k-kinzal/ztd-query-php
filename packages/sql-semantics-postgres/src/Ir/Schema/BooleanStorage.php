<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

/**
 * The builtin Boolean storage role of an ordinary PostgreSQL field.
 *
 * @visibility public
 * @example Reading the native type identity
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\BooleanStorage::Boolean->value // => 'pg_catalog.bool'
 */
enum BooleanStorage: string
{
    case Boolean = 'pg_catalog.bool';
}
