<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

/**
 * The identity of an admitted built-in PostgreSQL conversion target.
 *
 * @visibility public
 * @example Identifying the BOOLEAN cast target
 *     \SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination::Boolean->value // => 'bool'
 */
enum CastDestination: string
{
    case Integer = 'int4';
    case WideInteger = 'int8';
    case Boolean = 'bool';
    case Text = 'text';
}
