<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

/**
 * The direction of a transform function: from the SQL type to the language, or back.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtransform.html.
 *
 * @visibility public
 * @example Spelling the direction into the language
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection::FromSql->value // => 'FROM SQL'
 */
enum TransformDirection: string
{
    case FromSql = 'FROM SQL';
    case ToSql = 'TO SQL';
}
