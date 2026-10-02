<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

/**
 * The sign written before a signed number.
 *
 * Source: https://sqlite.org/syntax/signed-number.html.
 *
 * @visibility public
 * @example Reading the sign of a type argument
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT CAST(1 AS DECIMAL(-2))');
 *     $query->statement->columns[0]->expression->target->arguments[0]->sign // => \SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign::Minus
 */
enum NumberSign: string
{
    case Plus = '+';
    case Minus = '-';
}
