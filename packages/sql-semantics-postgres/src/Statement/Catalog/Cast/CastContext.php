<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

/**
 * Where a cast is applied without being written: in assignments, or implicitly in any context.
 *
 * A cast without AS ASSIGNMENT or AS IMPLICIT is applied only when written.
 * Source: https://www.postgresql.org/docs/17/sql-createcast.html.
 *
 * @visibility public
 * @example Spelling the implicit context
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastContext::Implicit->value // => 'IMPLICIT'
 */
enum CastContext: string
{
    case Assignment = 'ASSIGNMENT';
    case Implicit = 'IMPLICIT';
}
