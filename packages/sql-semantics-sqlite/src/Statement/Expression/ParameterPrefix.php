<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

/**
 * The characters a bind parameter starts with.
 *
 * Source: https://sqlite.org/lang_expr.html#parameters.
 *
 * @visibility public
 * @example Reading the prefix of a named parameter
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT :id');
 *     $query->statement->columns[0]->expression->prefix // => \SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix::Colon
 */
enum ParameterPrefix: string
{
    case Question = '?';
    case Colon = ':';
    case At = '@';
    case Dollar = '$';
}
