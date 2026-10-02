<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The pattern matching operators; each one calls the function of the same name.
 *
 * Source: https://sqlite.org/lang_expr.html#the_like_glob_regexp_match_and_extract_operators.
 *
 * @visibility public
 * @example Reading the operator of a pattern match
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT a GLOB 'x*' FROM t");
 *     $query->statement->columns[0]->expression->operator // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator::Glob
 */
enum PatternOperator: string
{
    case Like = 'LIKE';
    case Glob = 'GLOB';
    case Regexp = 'REGEXP';
    case Match = 'MATCH';
}
